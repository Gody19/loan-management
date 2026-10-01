<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;

class TrialBalanceService
{
    public function __construct(
        protected GeneralLedgerService $ledgerService,
    ) {}

    public function generate(
        int $organizationId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $periodId = null,
        ?int $branchId = null,
    ): array {
        $query = JournalLine::where('organization_id', $organizationId)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'));

        if ($periodId) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('accounting_period_id', $periodId));
        }
        if ($startDate) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '>=', $startDate));
        }
        if ($endDate) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $endDate));
        }

        // Optional branch narrowing. Omitting it keeps the organization-wide
        // behaviour every existing caller relies on.
        if ($branchId !== null) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('branch_id', $branchId));
        }

        $totals = $query->select('chart_of_account_id')
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('chart_of_account_id')
            ->get();

        $accountIds = $totals->pluck('chart_of_account_id')->toArray();
        $accounts = ChartOfAccount::whereIn('id', $accountIds)->get()->keyBy('id');

        $accountsData = [];
        foreach ($totals as $row) {
            $account = $accounts[$row->chart_of_account_id] ?? null;
            if (! $account) {
                continue;
            }

            $accountsData[] = [
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'account_type' => $account->account_type->label(),
                'debit_balance' => (float) $row->total_debit,
                'credit_balance' => (float) $row->total_credit,
            ];
        }

        usort($accountsData, fn ($a, $b) => strcmp($a['account_code'], $b['account_code']));

        $totalDebit = round(collect($accountsData)->sum('debit_balance'), 2);
        $totalCredit = round(collect($accountsData)->sum('credit_balance'), 2);

        return [
            'accounts' => $accountsData,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
        ];
    }
}
