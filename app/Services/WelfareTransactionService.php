<?php

namespace App\Services;

use App\Enums\FinancialTransactionStatus;
use App\Enums\WelfareAccountStatus;
use App\Enums\WelfareTransactionType;
use App\Models\WelfareAccount;
use App\Models\WelfareTransaction;
use Illuminate\Support\Facades\DB;

class WelfareTransactionService
{
    public function __construct(
        private TransactionNumberGenerator $numberGenerator,
        private AuditService $auditService,
    ) {}

    public function contribute(WelfareAccount $account, array $data): WelfareTransaction
    {
        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            throw \InvalidArgumentException('Contribution amount must be greater than zero.');
        }

        if ($account->status !== WelfareAccountStatus::Active) {
            throw \InvalidArgumentException('Welfare account is not active.');
        }

        return DB::transaction(function () use ($account, $data, $amount) {
            $lockedAccount = WelfareAccount::withoutGlobalScopes()
                ->where('id', $account->id)
                ->lockForUpdate()
                ->first();

            $balanceBefore = (float) $lockedAccount->current_balance;
            $balanceAfter = $balanceBefore + $amount;

            $transaction = WelfareTransaction::create([
                'welfare_account_id' => $lockedAccount->id,
                'member_id' => $lockedAccount->member_id,
                'organization_id' => $lockedAccount->organization_id,
                'branch_id' => $lockedAccount->branch_id,
                'vicoba_group_id' => $lockedAccount->vicoba_group_id,
                'transaction_number' => $this->numberGenerator->generate('WFT'),
                'transaction_type' => WelfareTransactionType::Contribution,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => FinancialTransactionStatus::Completed,
            ]);

            $lockedAccount->update(['current_balance' => $balanceAfter]);

            $this->auditService->log('welfare.contribution', $transaction, [], [
                'account_number' => $lockedAccount->account_number,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);

            return $transaction;
        });
    }

    public function benefit(WelfareAccount $account, array $data): WelfareTransaction
    {
        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            throw \InvalidArgumentException('Benefit amount must be greater than zero.');
        }

        if ($account->status !== WelfareAccountStatus::Active) {
            throw \InvalidArgumentException('Welfare account is not active.');
        }

        return DB::transaction(function () use ($account, $data, $amount) {
            $lockedAccount = WelfareAccount::withoutGlobalScopes()
                ->where('id', $account->id)
                ->lockForUpdate()
                ->first();

            $balanceBefore = (float) $lockedAccount->current_balance;

            if ($balanceBefore < $amount) {
                throw \InvalidArgumentException('Insufficient welfare balance.');
            }

            $balanceAfter = $balanceBefore - $amount;

            $transaction = WelfareTransaction::create([
                'welfare_account_id' => $lockedAccount->id,
                'member_id' => $lockedAccount->member_id,
                'organization_id' => $lockedAccount->organization_id,
                'branch_id' => $lockedAccount->branch_id,
                'vicoba_group_id' => $lockedAccount->vicoba_group_id,
                'transaction_number' => $this->numberGenerator->generate('WFT'),
                'transaction_type' => WelfareTransactionType::Benefit,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => FinancialTransactionStatus::Completed,
            ]);

            $lockedAccount->update(['current_balance' => $balanceAfter]);

            $this->auditService->log('welfare.benefit', $transaction, [], [
                'account_number' => $lockedAccount->account_number,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);

            return $transaction;
        });
    }

    public function reverse(WelfareTransaction $transaction, string $reason): WelfareTransaction
    {
        if ($transaction->status === FinancialTransactionStatus::Reversed) {
            throw \InvalidArgumentException('Transaction is already reversed.');
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $lockedAccount = WelfareAccount::withoutGlobalScopes()
                ->where('id', $transaction->welfare_account_id)
                ->lockForUpdate()
                ->first();

            $balanceBefore = (float) $lockedAccount->current_balance;

            $isDebit = $transaction->transaction_type->isDebit();
            $balanceAfter = $isDebit
                ? $balanceBefore + (float) $transaction->amount
                : $balanceBefore - (float) $transaction->amount;

            $reversal = WelfareTransaction::create([
                'welfare_account_id' => $lockedAccount->id,
                'member_id' => $lockedAccount->member_id,
                'organization_id' => $lockedAccount->organization_id,
                'branch_id' => $lockedAccount->branch_id,
                'vicoba_group_id' => $lockedAccount->vicoba_group_id,
                'transaction_number' => $this->numberGenerator->generate('WFT'),
                'transaction_type' => WelfareTransactionType::Reversal,
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

            $this->auditService->log('welfare.reversal', $reversal, [
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
