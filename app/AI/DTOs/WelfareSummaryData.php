<?php

namespace App\AI\DTOs;

/**
 * Welfare account balances for a member from stored authoritative balances.
 */
final class WelfareSummaryData
{
    /**
     * @param  array<int, array<string, mixed>>  $accounts
     */
    public function __construct(
        public readonly string $memberNumber,
        public readonly string $memberName,
        public readonly array $accounts,
        public readonly int $count,
        public readonly float $totalBalance,
        public readonly string $source = 'WelfareAccount.current_balance',
    ) {}

    public function toArray(): array
    {
        return [
            'member_number' => $this->memberNumber,
            'member_name' => $this->memberName,
            'accounts' => $this->accounts,
            'count' => $this->count,
            'total_balance' => $this->totalBalance,
            'source' => $this->source,
        ];
    }
}