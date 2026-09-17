<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class IncomeStatementService
{
    public function generate(
        int $organizationId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $periodId = null,
    ): array {
        $endDate = $endDate ?? now()->format('Y-m-d');
        $startDate = $startDate ?? now()->startOfYear()->format('Y-m-d');

        $query = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.chart_of_account_id')
            ->where('journal_lines.organization_id', $organizationId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '>=', $startDate)
            ->where('journal_entries.entry_date', '<=', $endDate)
            ->whereIn('chart_of_accounts.account_type', ['income', 'expense'])
            ->select(
                'chart_of_accounts.account_type',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit')
            )
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.account_type', 'chart_of_accounts.account_code', 'chart_of_accounts.account_name');

        if ($periodId) {
            $query->where('journal_entries.accounting_period_id', $periodId);
        }

        $rows = $query->get();

        $income = [];
        $expenses = [];

        foreach ($rows as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            if ($row->account_type === 'income') {
                $balance = $credit - $debit;
                if (abs($balance) > 0.01) {
                    $income[] = [
                        'code' => $row->account_code,
                        'name' => $row->account_name,
                        'amount' => round($balance, 2),
                    ];
                }
            } elseif ($row->account_type === 'expense') {
                $balance = $debit - $credit;
                if (abs($balance) > 0.01) {
                    $expenses[] = [
                        'code' => $row->account_code,
                        'name' => $row->account_name,
                        'amount' => round($balance, 2),
                    ];
                }
            }
        }

        usort($income, fn($a, $b) => strcmp($a['code'], $b['code']));
        usort($expenses, fn($a, $b) => strcmp($a['code'], $b['code']));

        $totalIncome = round(collect($income)->sum('amount'), 2);
        $totalExpenses = round(collect($expenses)->sum('amount'), 2);
        $netIncome = round($totalIncome - $totalExpenses, 2);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'income' => $income,
            'total_income' => $totalIncome,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'net_income' => $netIncome,
        ];
    }
}
