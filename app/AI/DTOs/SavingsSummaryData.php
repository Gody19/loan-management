<?php

namespace App\AI\DTOs;

/**
 * Savings account balances for a member. Totals are presented only from
 * stored authoritative balances; the tool never computes a derived figure.
 */
final class SavingsSummaryData
{
    /**
     * @param  array<int, array<string, mixed>>  $accounts
     */
    public function __construct(
        public readonly string $memberNumber,
        public readonly string $memberName,
        public readonly array $accounts,
        public readonly int $count,
        public readonly float $totalSavings,
        public readonly string $source = 'SavingsAccount.current_balance',
    ) {}

    public function toArray(): array
    {
        return [
            'member_number' => $this->memberNumber,
            'member_name' => $this->memberName,
            'accounts' => $this->accounts,
            'count' => $this->count,
            'total_savings' => $this->totalSavings,
            'source' => $this->source,
        ];
    }
}