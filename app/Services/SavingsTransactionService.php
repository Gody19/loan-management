<?php

namespace App\Services;

use App\Enums\FinancialTransactionStatus;
use App\Enums\SavingsAccountStatus;
use App\Enums\SavingsTransactionType;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use Illuminate\Support\Facades\DB;

class SavingsTransactionService
{
    public function __construct(
        private TransactionNumberGenerator $numberGenerator,
        private AuditService $auditService,
    ) {}

    public function deposit(SavingsAccount $account, array $data): SavingsTransaction
    {
        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            throw \InvalidArgumentException('Deposit amount must be greater than zero.');
        }

        if ($account->status !== SavingsAccountStatus::Active) {
            throw \InvalidArgumentException('Savings account is not active.');
        }

        return DB::transaction(function () use ($account, $data, $amount) {
            $lockedAccount = SavingsAccount::withoutGlobalScopes()
                ->where('id', $account->id)
                ->lockForUpdate()
                ->first();

            $balanceBefore = (float) $lockedAccount->current_balance;
            $balanceAfter = $balanceBefore + $amount;

            $transaction = SavingsTransaction::create([
                'savings_account_id' => $lockedAccount->id,
                'member_id' => $lockedAccount->member_id,
                'organization_id' => $lockedAccount->organization_id,
                'branch_id' => $lockedAccount->branch_id,
                'vicoba_group_id' => $lockedAccount->vicoba_group_id,
                'transaction_number' => $this->numberGenerator->generate('SVT'),
                'transaction_type' => SavingsTransactionType::Deposit,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => FinancialTransactionStatus::Completed,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $lockedAccount->update(['current_balance' => $balanceAfter]);

            $this->auditService->log('savings.deposit', $transaction, [], [
                'account_number' => $lockedAccount->account_number,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);

            return $transaction;
        });
    }

    public function withdraw(SavingsAccount $account, array $data): SavingsTransaction
    {
        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            throw \InvalidArgumentException('Withdrawal amount must be greater than zero.');
        }

        if ($account->status !== SavingsAccountStatus::Active) {
            throw \InvalidArgumentException('Savings account is not active.');
        }

        $product = $account->product;

        if ($product && ! $product->allow_withdrawal) {
            throw \InvalidArgumentException('Withdrawals are not allowed for this savings product.');
        }

        return DB::transaction(function () use ($account, $data, $amount, $product) {
            $lockedAccount = SavingsAccount::withoutGlobalScopes()
                ->where('id', $account->id)
                ->lockForUpdate()
                ->first();

            $balanceBefore = (float) $lockedAccount->current_balance;

            if ($balanceBefore < $amount) {
                throw \InvalidArgumentException('Insufficient balance.');
            }

            $balanceAfter = $balanceBefore - $amount;

            $minimumBalance = $product ? (float) $product->minimum_balance : 0;
            if ($balanceAfter < $minimumBalance) {
                throw \InvalidArgumentException(
                    "Withdrawal would bring balance below the minimum required balance of {$minimumBalance}."
                );
            }

            $transaction = SavingsTransaction::create([
                'savings_account_id' => $lockedAccount->id,
                'member_id' => $lockedAccount->member_id,
                'organization_id' => $lockedAccount->organization_id,
                'branch_id' => $lockedAccount->branch_id,
                'vicoba_group_id' => $lockedAccount->vicoba_group_id,
                'transaction_number' => $this->numberGenerator->generate('SVT'),
                'transaction_type' => SavingsTransactionType::Withdrawal,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => FinancialTransactionStatus::Completed,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $lockedAccount->update(['current_balance' => $balanceAfter]);

            $this->auditService->log('savings.withdrawal', $transaction, [], [
                'account_number' => $lockedAccount->account_number,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);

            return $transaction;
        });
    }

    public function reverse(SavingsTransaction $transaction, string $reason): SavingsTransaction
    {
        if ($transaction->status === FinancialTransactionStatus::Reversed) {
            throw \InvalidArgumentException('Transaction is already reversed.');
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $lockedAccount = SavingsAccount::withoutGlobalScopes()
                ->where('id', $transaction->savings_account_id)
                ->lockForUpdate()
                ->first();

            $balanceBefore = (float) $lockedAccount->current_balance;

            $isDebit = $transaction->transaction_type->isDebit();
            $balanceAfter = $isDebit
                ? $balanceBefore + (float) $transaction->amount
                : $balanceBefore - (float) $transaction->amount;

            $reversal = SavingsTransaction::create([
                'savings_account_id' => $lockedAccount->id,
                'member_id' => $lockedAccount->member_id,
                'organization_id' => $lockedAccount->organization_id,
                'branch_id' => $lockedAccount->branch_id,
                'vicoba_group_id' => $lockedAccount->vicoba_group_id,
                'transaction_number' => $this->numberGenerator->generate('SVT'),
                'transaction_type' => SavingsTransactionType::Reversal,
                'amount' => (float) $transaction->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'transaction_date' => now()->toDateString(),
                'description' => $reason,
                'status' => FinancialTransactionStatus::Completed,
                'reversed_transaction_id' => $transaction->id,
            ]);

            $lockedAccount->update(['current_balance' => $balanceAfter]);

            $transaction->update(['status' => FinancialTransactionStatus::Reversed]);

            $this->auditService->log('savings.reversal', $reversal, [
                'original_transaction_number' => $transaction->transaction_number,
                'original_amount' => $transaction->amount,
            ], [
                'account_number' => $lockedAccount->account_number,
                'amount' => $transaction->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reason' => $reason,
            ]);

            return $reversal;
        });
    }
}
