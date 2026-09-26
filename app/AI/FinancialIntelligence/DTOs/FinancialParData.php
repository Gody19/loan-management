<?php

namespace App\AI\FinancialIntelligence\DTOs;

/**
 * Portfolio-at-risk (PAR) summary broken into the canonical aging buckets.
 * PAR percentages follow FinancePro's convention: bucket principal outstanding
 * divided by total principal outstanding of active, non-fully-paid loans.
 */
final class FinancialParData
{
    /**
     * @param  array<int, array<string, mixed>>  $buckets
     */
    public function __construct(
        public readonly string $currency,
        public readonly int $organizationCount,
        public readonly int $activeLoansCount,
        public readonly float $totalPrincipalOutstanding,
        public readonly int $parThresholdDays,
        public readonly float $parRateOverThreshold,
        public readonly array $buckets,
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
            'total_principal_outstanding' => $this->totalPrincipalOutstanding,
            'par_threshold_days' => $this->parThresholdDays,
            'par_rate_over_threshold' => $this->parRateOverThreshold,
            'buckets' => $this->buckets,
            'generated_at' => $this->generatedAt,
        ];
    }
}
