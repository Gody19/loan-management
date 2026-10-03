<?php

namespace App\AI\FinancialIntelligence\Services;

use App\Models\Organization;
use App\Services\BalanceSheetService;
use App\Services\IncomeStatementService;
use App\Services\TrialBalanceService;
use Throwable;

/**
 * Full accounting statements for the AI, delegated to the existing
 * authoritative FinancePro report services.
 *
 * Why this exists: ai.accounting.view collapses a statement to three scalars
 * and a boolean, discarding the line detail. "Show me our income statement"
 * therefore had no capability it could be routed to, and fell through to an
 * ungrounded model call. This service exposes the same statements the FinancePro
 * accounting screens already show — it computes nothing and recalculates
 * nothing; every figure originates in IncomeStatementService,
 * BalanceSheetService or TrialBalanceService.
 *
 * Discipline inherited from AccountingIntelligenceService:
 *  - organizations whose accounting is not configured are skipped, never
 *    reported as zero;
 *  - tenant scope comes exclusively from the trusted AiContextData, so a
 *    prompt can never steer this at another organization;
 *  - statements are per-organization and carry their own totals, so an
 *    aggregate is never presented as a single entity's statement;
 *  - line items are capped and any omission is reported explicitly, so a
 *    truncated statement is never presented as a complete one.
 */
class AccountingStatementService
{
    /**
     * Organizations considered per request, mirroring AccountingIntelligenceService.
     */
    private const MAX_ORGANIZATIONS = 20;

    /**
     * Maximum statement lines carried per organization.
     */
    private const MAX_LINES = 40;

    public function __construct(
        private readonly IncomeStatementService $incomeStatement,
        private readonly BalanceSheetService $balanceSheet,
        private readonly TrialBalanceService $trialBalance,
    ) {}

    /**
     * @param  int[]  $organizationIds
     * @return array<string, mixed>
     */
    public function incomeStatement(array $organizationIds, ?string $from = null, ?string $to = null): array
    {
        $start = $this->date($from);
        $end = $this->date($to);

        $statements = [];

        foreach ($this->organizations($organizationIds) as $organization) {
            try {
                $generated = $this->incomeStatement->generate($organization->id, $start, $end);
            } catch (Throwable) {
                continue;
            }

            $income = $this->cap($generated['income'] ?? [], 'amount');
            $expenses = $this->cap($generated['expenses'] ?? [], 'amount');

            $statements[] = [
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
                'start_date' => $generated['start_date'] ?? $start,
                'end_date' => $generated['end_date'] ?? $end,
                'income_lines' => $income['lines'],
                'total_income' => round((float) ($generated['total_income'] ?? 0), 2),
                'expense_lines' => $expenses['lines'],
                'total_expenses' => round((float) ($generated['total_expenses'] ?? 0), 2),
                'net_income' => round((float) ($generated['net_income'] ?? 0), 2),
                'income_lines_omitted' => $income['omitted'],
                'expense_lines_omitted' => $expenses['omitted'],
            ];
        }

        return $this->envelope('income_statement', $statements, [
            'total_income' => round(collect($statements)->sum('total_income'), 2),
            'total_expenses' => round(collect($statements)->sum('total_expenses'), 2),
            'net_income' => round(collect($statements)->sum('net_income'), 2),
        ]);
    }

    /**
     * @param  int[]  $organizationIds
     * @return array<string, mixed>
     */
    public function balanceSheet(array $organizationIds, ?string $asOf = null): array
    {
        $asOfDate = $this->date($asOf);

        $statements = [];

        foreach ($this->organizations($organizationIds) as $organization) {
            try {
                $generated = $this->balanceSheet->generate($organization->id, $asOfDate);
            } catch (Throwable) {
                continue;
            }

            $assets = $this->cap($generated['assets'] ?? [], 'balance');
            $liabilities = $this->cap($generated['liabilities'] ?? [], 'balance');
            $equity = $this->cap($generated['equity'] ?? [], 'balance');

            $statements[] = [
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
                'as_of_date' => $generated['as_of_date'] ?? $asOfDate,
                'asset_lines' => $assets['lines'],
                'total_assets' => round((float) ($generated['total_assets'] ?? 0), 2),
                'liability_lines' => $liabilities['lines'],
                'total_liabilities' => round((float) ($generated['total_liabilities'] ?? 0), 2),
                'equity_lines' => $equity['lines'],
                'total_equity' => round((float) ($generated['total_equity'] ?? 0), 2),
                'net_income' => round((float) ($generated['net_income'] ?? 0), 2),
                'is_balanced' => (bool) ($generated['is_balanced'] ?? false),
                'asset_lines_omitted' => $assets['omitted'],
                'liability_lines_omitted' => $liabilities['omitted'],
                'equity_lines_omitted' => $equity['omitted'],
            ];
        }

        return $this->envelope('balance_sheet', $statements, [
            'total_assets' => round(collect($statements)->sum('total_assets'), 2),
            'total_liabilities' => round(collect($statements)->sum('total_liabilities'), 2),
            'total_equity' => round(collect($statements)->sum('total_equity'), 2),
            'all_balanced' => $statements !== [] && ! in_array(false, array_column($statements, 'is_balanced'), true),
        ]);
    }

    /**
     * @param  int[]  $organizationIds
     * @return array<string, mixed>
     */
    public function trialBalance(array $organizationIds, ?string $from = null, ?string $to = null): array
    {
        $start = $this->date($from);
        $end = $this->date($to);

        $statements = [];

        foreach ($this->organizations($organizationIds) as $organization) {
            try {
                $generated = $this->trialBalance->generate($organization->id, $start, $end);
            } catch (Throwable) {
                continue;
            }

            $accounts = $this->cap($generated['accounts'] ?? [], 'debit_balance');

            $statements[] = [
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
                'account_lines' => $accounts['lines'],
                'total_debit' => round((float) ($generated['total_debit'] ?? 0), 2),
                'total_credit' => round((float) ($generated['total_credit'] ?? 0), 2),
                'is_balanced' => (bool) ($generated['is_balanced'] ?? false),
                'account_lines_omitted' => $accounts['omitted'],
            ];
        }

        return $this->envelope('trial_balance', $statements, [
            'total_debit' => round(collect($statements)->sum('total_debit'), 2),
            'total_credit' => round(collect($statements)->sum('total_credit'), 2),
            'all_balanced' => $statements !== [] && ! in_array(false, array_column($statements, 'is_balanced'), true),
        ]);
    }

    /**
     * Resolved organizations, bounded and existence-checked.
     *
     * @param  int[]  $organizationIds
     * @return Organization[]
     */
    protected function organizations(array $organizationIds): array
    {
        return Organization::whereIn('id', array_slice($organizationIds, 0, self::MAX_ORGANIZATIONS))
            ->get()
            ->all();
    }

    /**
     * Strict Y-m-d validation. An unparseable or absent date becomes null so
     * the authoritative service applies its own documented default. A date is
     * never guessed, inferred, or derived from question text.
     */
    protected function date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value ? $value : null;
    }

    /**
     * Cap line items and report how many were omitted, so a truncated statement
     * is never presented as a complete one.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{lines: array<int, array<string, mixed>>, omitted: int}
     */
    protected function cap(array $lines, string $amountKey): array
    {
        $kept = [];

        foreach (array_slice($lines, 0, self::MAX_LINES) as $line) {
            $line[$amountKey] = round((float) ($line[$amountKey] ?? 0), 2);
            $kept[] = $line;
        }

        return [
            'lines' => $kept,
            'omitted' => max(0, count($lines) - self::MAX_LINES),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $statements
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    protected function envelope(string $statement, array $statements, array $totals): array
    {
        return [
            'statement' => $statement,
            'currency' => (string) config('financial-intelligence.currency', 'TZS'),
            'generated_at' => now()->toISOString(),
            'organization_count' => count($statements),
            'statements' => $statements,
            'totals' => $totals,
        ];
    }
}
