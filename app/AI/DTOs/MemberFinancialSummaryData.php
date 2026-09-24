<?php

namespace App\AI\DTOs;

/**
 * Authoritative member financial snapshot passed through from
 * FinancialStatementService::getMemberFinancialSummary unchanged. Values are
 * authoritative aggregates the AI may explain but never recompute.
 */
final class MemberFinancialSummaryData
{
    public function __construct(
        public readonly string $memberNumber,
        public readonly string $memberName,
        public readonly float $totalSavings,
        public readonly int $savingsAccountsCount,
        public readonly int $totalShares,
        public readonly float $totalShareValue,
        public readonly float $welfareBalance,
        public readonly string $source = 'FinancialStatementService',
    ) {}

    public function toArray(): array
    {
        return [
            'member_number' => $this->memberNumber,
            'member_name' => $this->memberName,
            'total_savings' => $this->totalSavings,
            'savings_accounts_count' => $this->savingsAccountsCount,
            'total_shares' => $this->totalShares,
            'total_share_value' => $this->totalShareValue,
            'welfare_balance' => $this->welfareBalance,
            'source' => $this->source,
        ];
    }
}