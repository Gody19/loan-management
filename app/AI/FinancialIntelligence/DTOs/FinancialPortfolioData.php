<?php

namespace App\AI\FinancialIntelligence\DTOs;

/**
 * Portfolio composition snapshot from stored authoritative loan columns.
 * All figures are re-read from FinancePro loans on demand; nothing here is
 * cached or derived from a second source of truth.
 */
final class FinancialPortfolioData
{
    /**
     * @param  array<int, array<string, mixed>>  $compositionByStatus
     * @param  array<int, array<string, mixed>>  $compositionByPlan
     */
    public function __construct(
        public readonly string $currency,
        public readonly int $organizationCount,
        public readonly int $activeLoansCount,
        public readonly float $totalOutstanding,
        public readonly float $totalPrincipalDisbursed,
        public readonly float $maturingWithin30Days,
        public readonly float $maturingWithin60Days,
        public readonly array $compositionByStatus,
        public readonly array $compositionByPlan,
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
            'active_loans_count' => $this->activeLoansCount,
            'total_outstanding' => $this->totalOutstanding,
            'total_principal_disbursed' => $this->totalPrincipalDisbursed,
            'maturing_within_30_days' => $this->maturingWithin30Days,
            'maturing_within_60_days' => $this->maturingWithin60Days,
            'composition_by_status' => $this->compositionByStatus,
            'composition_by_plan' => $this->compositionByPlan,
            'generated_at' => $this->generatedAt,
        ];
    }
}
