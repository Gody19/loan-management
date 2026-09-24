<?php

namespace App\AI\DTOs;

/**
 * Share account holdings for a member. Values are the stored total_shares /
 * total_value figures; the AI explains them, never recalcs them.
 */
final class ShareSummaryData
{
    /**
     * @param  array<int, array<string, mixed>>  $accounts
     */
    public function __construct(
        public readonly string $memberNumber,
        public readonly string $memberName,
        public readonly array $accounts,
        public readonly int $count,
        public readonly int $totalShares,
        public readonly float $totalShareValue,
        public readonly string $source = 'ShareAccount.total_shares/total_value',
    ) {}

    public function toArray(): array
    {
        return [
            'member_number' => $this->memberNumber,
            'member_name' => $this->memberName,
            'accounts' => $this->accounts,
            'count' => $this->count,
            'total_shares' => $this->totalShares,
            'total_share_value' => $this->totalShareValue,
            'source' => $this->source,
        ];
    }
}