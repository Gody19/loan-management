<?php

namespace App\Services;

use App\Enums\FinancialTransactionStatus;
use App\Enums\ShareAccountStatus;
use App\Enums\ShareTransactionType;
use App\Models\PaymentMethod;
use App\Models\ShareAccount;
use App\Models\ShareTransaction;
use Illuminate\Support\Facades\DB;

class ShareTransactionService
{
    public function __construct(
        private TransactionNumberGenerator $numberGenerator,
        private AuditService $auditService,
    ) {}

    public function purchase(ShareAccount $account, array $data): ShareTransaction
    {
        $quantity = (int) $data['quantity'];

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Share quantity must be greater than zero.');
        }

        if ($account->status !== ShareAccountStatus::Active) {
            throw new \InvalidArgumentException('Share account is not active.');
        }

        $product = $account->product;
        $sharePrice = (float) $data['share_price'];

        if ($product && abs($sharePrice - (float) $product->share_price) > 0.001) {
            throw new \InvalidArgumentException('Provided share price does not match the product share price.');
        }

        if (isset($data['payment_method_id'])) {
            $paymentMethod = PaymentMethod::find($data['payment_method_id']);
            if (! $paymentMethod || $paymentMethod->status !== 'active') {
                throw new \InvalidArgumentException('Selected payment method is not active.');
            }
            if ($paymentMethod->organization_id !== $account->organization_id) {
                throw new \InvalidArgumentException('Selected payment method does not belong to this organization.');
            }
        }

        $expectedAmount = $quantity * $sharePrice;
        $providedAmount = isset($data['amount']) ? (float) $data['amount'] : $expectedAmount;

        if (abs($providedAmount - $expectedAmount) > 0.001) {
            throw new \InvalidArgumentException(
                'Amount does not match the expected value of quantity × share_price ('.$expectedAmount.').'
            );
        }

        return DB::transaction(function () use ($account, $data, $quantity, $sharePrice, $expectedAmount) {
            $lockedAccount = ShareAccount::withoutGlobalScopes()
                ->where('id', $account->id)
                ->lockForUpdate()
                ->first();

            $sharesBefore = $lockedAccount->total_shares;
            $valueBefore = (float) $lockedAccount->total_value;

            $sharesAfter = $sharesBefore + $quantity;
            $valueAfter = $valueBefore + $expectedAmount;

            $transaction = ShareTransaction::create([
                'share_account_id' => $lockedAccount->id,
                'member_id' => $lockedAccount->member_id,
                'organization_id' => $lockedAccount->organization_id,
                'branch_id' => $lockedAccount->branch_id,
                'vicoba_group_id' => $lockedAccount->vicoba_group_id,
                'transaction_number' => $this->numberGenerator->generate('SHT'),
                'transaction_type' => ShareTransactionType::Purchase,
                'quantity' => $quantity,
                'share_price' => $sharePrice,
                'amount' => $expectedAmount,
                'balance_shares_before' => $sharesBefore,
                'balance_shares_after' => $sharesAfter,
                'balance_value_before' => $valueBefore,
                'balance_value_after' => $valueAfter,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => FinancialTransactionStatus::Completed,
            ]);

            $lockedAccount->update([
                'total_shares' => $sharesAfter,
                'total_value' => $valueAfter,
            ]);

            $this->auditService->log('shares.purchase', $transaction, [], [
                'account_number' => $lockedAccount->account_number,
                'quantity' => $quantity,
                'share_price' => $sharePrice,
                'amount' => $expectedAmount,
                'total_shares_before' => $sharesBefore,
                'total_shares_after' => $sharesAfter,
            ]);

            return $transaction;
        });
    }

    public function redeem(ShareAccount $account, array $data): ShareTransaction
    {
        $quantity = (int) $data['quantity'];

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Share quantity must be greater than zero.');
        }

        if ($account->status !== ShareAccountStatus::Active) {
            throw new \InvalidArgumentException('Share account is not active.');
        }

        if (isset($data['payment_method_id'])) {
            $paymentMethod = PaymentMethod::find($data['payment_method_id']);
            if (! $paymentMethod || $paymentMethod->status !== 'active') {
                throw new \InvalidArgumentException('Selected payment method is not active.');
            }
            if ($paymentMethod->organization_id !== $account->organization_id) {
                throw new \InvalidArgumentException('Selected payment method does not belong to this organization.');
            }
        }

        return DB::transaction(function () use ($account, $data, $quantity) {
            $lockedAccount = ShareAccount::withoutGlobalScopes()
                ->where('id', $account->id)
                ->lockForUpdate()
                ->first();

            if ($lockedAccount->total_shares < $quantity) {
                throw new \InvalidArgumentException('Insufficient shares for redemption.');
            }

            $product = $lockedAccount->product;
            $sharePrice = (float) ($product ? $product->share_price : 0);

            $sharesBefore = $lockedAccount->total_shares;
            $valueBefore = (float) $lockedAccount->total_value;

            $sharesAfter = $sharesBefore - $quantity;
            $valueAfter = $valueBefore - ($quantity * $sharePrice);

            if ($product && $sharesAfter < $product->minimum_shares) {
                throw new \InvalidArgumentException(
                    'Redemption would bring shares below the minimum required ('.$product->minimum_shares.').'
                );
            }

            $amount = $quantity * $sharePrice;

            $transaction = ShareTransaction::create([
                'share_account_id' => $lockedAccount->id,
                'member_id' => $lockedAccount->member_id,
                'organization_id' => $lockedAccount->organization_id,
                'branch_id' => $lockedAccount->branch_id,
                'vicoba_group_id' => $lockedAccount->vicoba_group_id,
                'transaction_number' => $this->numberGenerator->generate('SHT'),
                'transaction_type' => ShareTransactionType::Redeem,
                'quantity' => $quantity,
                'share_price' => $sharePrice,
                'amount' => $amount,
                'balance_shares_before' => $sharesBefore,
                'balance_shares_after' => $sharesAfter,
                'balance_value_before' => $valueBefore,
                'balance_value_after' => $valueAfter,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => FinancialTransactionStatus::Completed,
            ]);

            $lockedAccount->update([
                'total_shares' => $sharesAfter,
                'total_value' => max(0, $valueAfter),
            ]);

            $this->auditService->log('shares.redemption', $transaction, [], [
                'account_number' => $lockedAccount->account_number,
                'quantity' => $quantity,
                'share_price' => $sharePrice,
                'amount' => $amount,
                'total_shares_before' => $sharesBefore,
                'total_shares_after' => $sharesAfter,
            ]);

            return $transaction;
        });
    }

    public function reverse(ShareTransaction $transaction, string $reason): ShareTransaction
    {
        if ($transaction->status === FinancialTransactionStatus::Reversed) {
            throw new \InvalidArgumentException('Transaction is already reversed.');
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $lockedAccount = ShareAccount::withoutGlobalScopes()
                ->where('id', $transaction->share_account_id)
                ->lockForUpdate()
                ->first();

            $sharesBefore = $lockedAccount->total_shares;
            $valueBefore = (float) $lockedAccount->total_value;

            $isPurchase = $transaction->transaction_type === ShareTransactionType::Purchase;
            $sharesAfter = $isPurchase
                ? $sharesBefore - $transaction->quantity
                : $sharesBefore + $transaction->quantity;
            $valueAfter = $isPurchase
                ? $valueBefore - (float) $transaction->amount
                : $valueBefore + (float) $transaction->amount;

            $reversal = ShareTransaction::create([
                'share_account_id' => $lockedAccount->id,
                'member_id' => $lockedAccount->member_id,
                'organization_id' => $lockedAccount->organization_id,
                'branch_id' => $lockedAccount->branch_id,
                'vicoba_group_id' => $lockedAccount->vicoba_group_id,
                'transaction_number' => $this->numberGenerator->generate('SHT'),
                'transaction_type' => ShareTransactionType::Reversal,
                'quantity' => $transaction->quantity,
                'share_price' => $transaction->share_price,
                'amount' => (float) $transaction->amount,
                'balance_shares_before' => $sharesBefore,
                'balance_shares_after' => max(0, $sharesAfter),
                'balance_value_before' => $valueBefore,
                'balance_value_after' => max(0, $valueAfter),
                'transaction_date' => now()->toDateString(),
                'description' => $reason,
                'status' => FinancialTransactionStatus::Completed,
                'reversed_transaction_id' => $transaction->id,
            ]);

            $lockedAccount->update([
                'total_shares' => max(0, $sharesAfter),
                'total_value' => max(0, $valueAfter),
            ]);

            $transaction->update([
                'status' => FinancialTransactionStatus::Reversed,
                'reversed_by' => auth()->id(),
            ]);

            $this->auditService->log('shares.reversal', $reversal, [
                'original_transaction_number' => $transaction->transaction_number,
                'original_quantity' => $transaction->quantity,
                'original_amount' => $transaction->amount,
            ], [
                'account_number' => $lockedAccount->account_number,
                'quantity' => $transaction->quantity,
                'amount' => $transaction->amount,
                'total_shares_before' => $sharesBefore,
                'total_shares_after' => max(0, $sharesAfter),
                'reason' => $reason,
            ]);

            return $reversal;
        });
    }
}
