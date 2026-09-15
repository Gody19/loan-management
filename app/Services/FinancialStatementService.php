<?php

namespace App\Services;

use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\ShareAccount;
use App\Models\WelfareAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FinancialStatementService
{
    public function getSavingsStatement(
        SavingsAccount $account,
        ?string $from,
        ?string $to,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $account->transactions()
            ->with(['paymentMethod', 'creator'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        if ($from) {
            $query->where('transaction_date', '>=', $from);
        }

        if ($to) {
            $query->where('transaction_date', '<=', $to);
        }

        return $query->paginate($perPage);
    }

    public function getShareStatement(
        ShareAccount $account,
        ?string $from,
        ?string $to,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $account->transactions()
            ->with(['paymentMethod', 'creator'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        if ($from) {
            $query->where('transaction_date', '>=', $from);
        }

        if ($to) {
            $query->where('transaction_date', '<=', $to);
        }

        return $query->paginate($perPage);
    }

    public function getWelfareStatement(
        WelfareAccount $account,
        ?string $from,
        ?string $to,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $account->transactions()
            ->with(['paymentMethod', 'creator'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        if ($from) {
            $query->where('transaction_date', '>=', $from);
        }

        if ($to) {
            $query->where('transaction_date', '<=', $to);
        }

        return $query->paginate($perPage);
    }

    public function getMemberFinancialSummary(Member $member): array
    {
        $savingsAccounts = $member->savingsAccounts()->get();
        $shareAccounts = $member->shareAccounts()->get();
        $welfareAccounts = $member->welfareAccounts()->get();

        return [
            'total_savings' => (float) $savingsAccounts->sum('current_balance'),
            'savings_accounts_count' => $savingsAccounts->count(),
            'total_shares' => (int) $shareAccounts->sum('total_shares'),
            'total_share_value' => (float) $shareAccounts->sum('total_value'),
            'welfare_balance' => (float) $welfareAccounts->sum('current_balance'),
        ];
    }
}
