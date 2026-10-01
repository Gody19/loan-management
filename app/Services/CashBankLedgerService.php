<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class CashBankLedgerService
{
    public function generate(
        int $organizationId,
        int $accountId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
    ): array {
        $account = ChartOfAccount::where('id', $accountId)
            ->where('organization_id', $organizationId)
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException('Account not found.');
        }

        $startDate = $startDate ?? now()->startOfYear()->format('Y-m-d');
        $endDate = $endDate ?? now()->format('Y-m-d');

        $openingBalance = $this->getOpeningBalance($organizationId, $accountId, $startDate, $branchId);

        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.organization_id', $organizationId)
            ->where('journal_lines.chart_of_account_id', $accountId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '>=', $startDate)
            ->where('journal_entries.entry_date', '<=', $endDate)
            ->select(
                'journal_entries.entry_date',
                'journal_entries.journal_number',
                'journal_lines.description',
                'journal_lines.debit',
                'journal_lines.credit'
            );

        // Optional branch narrowing. Omitting it keeps the organization-wide
        // behaviour every existing caller relies on.
        if ($branchId !== null) {
            $lines->where('journal_entries.branch_id', $branchId);
        }

        $lines = $lines
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->get();

        $runningBalance = $openingBalance;
        $entries = [];
        $totalReceipts = 0;
        $totalPayments = 0;

        foreach ($lines as $line) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;

            $runningBalance += $debit - $credit;

            if ($debit > 0) {
                $totalReceipts += $debit;
            }
            if ($credit > 0) {
                $totalPayments += $credit;
            }

            $entries[] = [
                'date' => $line->entry_date,
                'journal_number' => $line->journal_number,
                'description' => $line->description,
                'receipt' => $debit > 0 ? $debit : null,
                'payment' => $credit > 0 ? $credit : null,
                'running_balance' => round($runningBalance, 2),
            ];
        }

        return [
            'account' => $account,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'opening_balance' => round($openingBalance, 2),
            'entries' => $entries,
            'total_receipts' => round($totalReceipts, 2),
            'total_payments' => round($totalPayments, 2),
            'closing_balance' => round($runningBalance, 2),
        ];
    }

    protected function getOpeningBalance(int $organizationId, int $accountId, string $asOfDate, ?int $branchId = null): float
    {
        $account = ChartOfAccount::findOrFail($accountId);

        $query = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.organization_id', $organizationId)
            ->where('journal_lines.chart_of_account_id', $accountId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '<', $asOfDate);

        if ($branchId !== null) {
            $query->where('journal_entries.branch_id', $branchId);
        }

        $totals = $query
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $totalDebit = (float) ($totals->total_debit ?? 0);
        $totalCredit = (float) ($totals->total_credit ?? 0);

        if ($account->account_type->normalBalance() === 'debit') {
            return round($totalDebit - $totalCredit, 2);
        }

        return round($totalCredit - $totalDebit, 2);
    }
}
