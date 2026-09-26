<?php

namespace App\AI\FinancialIntelligence\DTOs;

/**
 * Summarized accounting position derived from the authoritative report
 * services (IncomeStatementService, TrialBalanceService,
 * GeneralLedgerService). Per-organization breakdown keeps the aggregate
 * traceable; organizations without accounting configuration are skipped,
 * never reported as zero by mistake.
 */
final class FinancialAccountingData
{
    /**
     * @param  array<int, array<string, mixed>>  $organizationBreakdown
     */
    public function __construct(
        public readonly string $currency,
        public readonly int $organizationCount,
        public readonly float $totalIncome,
        public readonly float $totalExpenses,
        public readonly float $netIncome,
        public readonly float $liquidPosition,
        public readonly bool $allBalanced,
        public readonly array $organizationBreakdown,
        public readonly ?string $generatedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'organization_count' => $this->organizationCount,
            'total_income' => $this->totalIncome,
            'total_expenses' => $this->totalExpenses,
            'net_income' => $this->netIncome,
            'liquid_position' => $this->liquidPosition,
            'all_balanced' => $this->allBalanced,
            'organization_breakdown' => $this->organizationBreakdown,
            'generated_at' => $this->generatedAt,
        ];
    }
}
