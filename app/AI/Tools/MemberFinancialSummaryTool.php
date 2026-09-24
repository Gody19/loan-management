<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\MemberFinancialSummaryData;
use App\AI\Services\AiToolAccessService;
use App\Models\User;
use App\Services\FinancialStatementService;

/**
 * ai.member.financial_summary — authoritative member financial snapshot.
 * Passes FinancialStatementService::getMemberFinancialSummary through
 * unchanged; the AI never recomputes the figures.
 */
class MemberFinancialSummaryTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
        private readonly FinancialStatementService $statements,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $member = $this->access->resolveMember($user, $context, $arguments['member_number'] ?? null);

        $summary = $this->statements->getMemberFinancialSummary($member);

        return (new MemberFinancialSummaryData(
            memberNumber: $member->member_number,
            memberName: $member->full_name,
            totalSavings: (float) $summary['total_savings'],
            savingsAccountsCount: (int) $summary['savings_accounts_count'],
            totalShares: (int) $summary['total_shares'],
            totalShareValue: (float) $summary['total_share_value'],
            welfareBalance: (float) $summary['welfare_balance'],
        ))->toArray();
    }
}