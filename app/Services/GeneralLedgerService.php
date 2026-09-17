<?php

namespace App\Services;

use App\Models\JournalLine;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class GeneralLedgerService
{
    public function getLedger(
        int $organizationId,
        ?int $accountId = null,
        ?int $branchId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $periodId = null,
    ): array {
        $query = JournalLine::where('journal_lines.organization_id', $organizationId)
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted'))
            ->with(['journalEntry', 'account']);

        if ($accountId) {
            $query->where('chart_of_account_id', $accountId);
        }

        if ($branchId) {
            $query->whereHas('journalEntry', fn($q) => $q->where('branch_id', $branchId));
        }

        if ($periodId) {
            $query->whereHas('journalEntry', fn($q) => $q->where('accounting_period_id', $periodId));
        }

        if ($startDate) {
            $query->whereHas('journalEntry', fn($q) => $q->where('entry_date', '>=', $startDate));
        }

        if ($endDate) {
            $query->whereHas('journalEntry', fn($q) => $q->where('entry_date', '<=', $endDate));
        }

        $lines = $query->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->get(['journal_lines.*']);

        $runningBalance = 0;
        $entries = [];

        foreach ($lines as $line) {
            $account = $line->account;
            $normalBalance = $account->account_type->normalBalance();

            if ($normalBalance === 'debit') {
                $runningBalance += (float) $line->debit - (float) $line->credit;
            } else {
                $runningBalance += (float) $line->credit - (float) $line->debit;
            }

            $entries[] = [
                'date' => $line->journalEntry->entry_date,
                'journal_number' => $line->journalEntry->journal_number,
                'description' => $line->description ?? $line->journalEntry->description,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'running_balance' => round($runningBalance, 2),
            ];
        }

        return [
            'entries' => $entries,
            'total_debit' => round($lines->sum('debit'), 2),
            'total_credit' => round($lines->sum('credit'), 2),
        ];
    }

    public function getAccountBalance(int $organizationId, int $accountId): float
    {
        $account = ChartOfAccount::findOrFail($accountId);
        $normalBalance = $account->account_type->normalBalance();

        $totals = JournalLine::where('organization_id', $organizationId)
            ->where('chart_of_account_id', $accountId)
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted'))
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $totalDebit = (float) ($totals->total_debit ?? 0);
        $totalCredit = (float) ($totals->total_credit ?? 0);

        if ($normalBalance === 'debit') {
            return round($totalDebit - $totalCredit, 2);
        }

        return round($totalCredit - $totalDebit, 2);
    }

    public function getAccountBalances(int $organizationId): array
    {
        $accounts = ChartOfAccount::where('organization_id', $organizationId)
            ->active()
            ->orderBy('account_code')
            ->get();

        $balanceData = JournalLine::where('organization_id', $organizationId)
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted'))
            ->select('chart_of_account_id', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
            ->groupBy('chart_of_account_id')
            ->get()
            ->keyBy('chart_of_account_id');

        $balances = [];
        foreach ($accounts as $account) {
            $data = $balanceData[$account->id] ?? null;
            $totalDebit = (float) ($data->total_debit ?? 0);
            $totalCredit = (float) ($data->total_credit ?? 0);

            if ($account->account_type->normalBalance() === 'debit') {
                $balance = $totalDebit - $totalCredit;
            } else {
                $balance = $totalCredit - $totalDebit;
            }

            if (abs($balance) > 0.01 || in_array($account->account_type->value, ['asset', 'liability', 'equity'])) {
                $balances[] = [
                    'account' => $account,
                    'debit_balance' => $totalDebit,
                    'credit_balance' => $totalCredit,
                    'balance' => round($balance, 2),
                ];
            }
        }

        return $balances;
    }
}
