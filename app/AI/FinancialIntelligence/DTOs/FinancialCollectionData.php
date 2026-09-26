<?php

namespace App\AI\FinancialIntelligence\DTOs;

/**
 * Collection intelligence for a period: how much fell due on repayment
 * schedules versus what was actually collected, plus reversal activity.
 */
final class FinancialCollectionData
{
    public function __construct(
        public readonly string $currency,
        public readonly int $organizationCount,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly float $totalDue,
        public readonly float $totalCollected,
        public readonly float $collectionRate,
        public readonly int $postedCount,
        public readonly int $reversedCount,
        public readonly float $reversedAmount,
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
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'total_due' => $this->totalDue,
            'total_collected' => $this->totalCollected,
            'collection_rate' => $this->collectionRate,
            'posted_count' => $this->postedCount,
            'reversed_count' => $this->reversedCount,
            'reversed_amount' => $this->reversedAmount,
            'generated_at' => $this->generatedAt,
        ];
    }
}
