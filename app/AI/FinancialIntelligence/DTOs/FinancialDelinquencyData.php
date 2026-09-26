<?php

namespace App\AI\FinancialIntelligence\DTOs;

/**
 * Delinquency profile: how many loans are past due, the severity of the
 * oldest overdue installment (DPD), and the largest delinquent exposures.
 */
final class FinancialDelinquencyData
{
    /**
     * @param  array<int, array<string, mixed>>  $topDelinquentLoans
     */
    public function __construct(
        public readonly string $currency,
        public readonly int $organizationCount,
        public readonly int $delinquentLoansCount,
        public readonly float $delinquentPrincipalOutstanding,
        public readonly int $minDpd,
        public readonly int $maxDpd,
        public readonly float $averageDpd,
        public readonly array $topDelinquentLoans,
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
            'delinquent_loans_count' => $this->delinquentLoansCount,
            'delinquent_principal_outstanding' => $this->delinquentPrincipalOutstanding,
            'min_dpd' => $this->minDpd,
            'max_dpd' => $this->maxDpd,
            'average_dpd' => $this->averageDpd,
            'top_delinquent_loans' => $this->topDelinquentLoans,
            'generated_at' => $this->generatedAt,
        ];
    }
}
