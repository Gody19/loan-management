<?php

namespace App\AI\FinancialIntelligence\DTOs;

/**
 * Monthly disbursement / collection trend series. Each month records how much
 * was disbursed, how much was collected (posted repayments), and how many
 * reversals occurred — a bounded, deterministic view of money movements.
 */
final class FinancialTrendData
{
    /**
     * @param  array<int, array<string, mixed>>  $trend
     */
    public function __construct(
        public readonly string $currency,
        public readonly int $organizationCount,
        public readonly int $months,
        public readonly array $trend,
        public readonly float $totalDisbursed,
        public readonly float $totalCollected,
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
            'months' => $this->months,
            'trend' => $this->trend,
            'total_disbursed' => $this->totalDisbursed,
            'total_collected' => $this->totalCollected,
            'generated_at' => $this->generatedAt,
        ];
    }
}
