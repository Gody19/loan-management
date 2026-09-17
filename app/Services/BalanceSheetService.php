<?php

namespace App\Services;

use App\Enums\AccountType;
use Illuminate\Support\Facades\DB;

class BalanceSheetService
{
    public function __construct(
        protected GeneralLedgerService $ledgerService,
    ) {}

    public function generate(int $organizationId, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now()->format('Y-m-d');

        $query = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.chart_of_account_id')
            ->where('journal_lines.organization_id', $organizationId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '<=', $asOfDate)
            ->select(
                'chart_of_accounts.account_type',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit')
            )
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.account_type', 'chart_of_accounts.account_code', 'chart_of_accounts.account_name');

        $rows = $query->get();

        $assets = [];
        $liabilities = [];
        $equity = [];

        foreach ($rows as $row) {
            $type = $row->account_type;
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            if ($type === AccountType::Asset->value) {
                $balance = $debit - $credit;
                $assets[] = [
                    'code' => $row->account_code,
                    'name' => $row->account_name,
                    'balance' => round($balance, 2),
                ];
            } elseif ($type === AccountType::Liability->value) {
                $balance = $credit - $debit;
                $liabilities[] = [
                    'code' => $row->account_code,
                    'name' => $row->account_name,
                    'balance' => round($balance, 2),
                ];
            } elseif ($type === AccountType::Equity->value) {
                $balance = $credit - $debit;
                $equity[] = [
                    'code' => $row->account_code,
                    'name' => $row->account_name,
                    'balance' => round($balance, 2),
                ];
            }
        }

        $totalAssets = round(collect($assets)->sum('balance'), 2);
        $totalLiabilities = round(collect($liabilities)->sum('balance'), 2);
        $totalEquity = round(collect($equity)->sum('balance'), 2);

        $netIncome = $this->calculateNetIncome($organizationId, $asOfDate);
        $totalEquity += $netIncome;

        return [
            'as_of_date' => $asOfDate,
            'assets' => $assets,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilities,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equity,
            'net_income' => round($netIncome, 2),
            'total_equity' => round($totalEquity, 2),
            'is_balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
        ];
    }

    protected function calculateNetIncome(int $organizationId, string $asOfDate): float
    {
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.chart_of_account_id')
            ->where('journal_lines.organization_id', $organizationId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '<=', $asOfDate)
            ->whereIn('chart_of_accounts.account_type', ['income', 'expense'])
            ->select(
                'chart_of_accounts.account_type',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit')
            )
            ->groupBy('chart_of_accounts.account_type')
            ->get();

        $totalIncome = 0;
        $totalExpenses = 0;

        foreach ($rows as $row) {
            if ($row->account_type === 'income') {
                $totalIncome += (float) $row->total_credit - (float) $row->total_debit;
            } elseif ($row->account_type === 'expense') {
                $totalExpenses += (float) $row->total_debit - (float) $row->total_credit;
            }
        }

        return round($totalIncome - $totalExpenses, 2);
    }
}
