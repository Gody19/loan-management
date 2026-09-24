<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\ShareSummaryData;
use App\AI\Services\AiToolAccessService;
use App\Models\User;
use App\Services\FinancialStatementService;

/**
 * ai.member.share_summary — share holdings of a member from the stored
 * authoritative totals; the aggregate mirrors FinancialStatementService.
 */
class ShareSummaryTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
        private readonly FinancialStatementService $statements,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $member = $this->access->resolveMember($user, $context, $arguments['member_number'] ?? null);

        $accounts = $member->shareAccounts()->get()->take(50)->values();

        $rows = $accounts->map(function ($account) {
            return [
                'account_number' => $account->account_number,
                'status' => $account->status?->value,
                'total_shares' => (int) $account->total_shares,
                'total_value' => (float) $account->total_value,
            ];
        })->all();

        $summary = $this->statements->getMemberFinancialSummary($member);

        return (new ShareSummaryData(
            memberNumber: $member->member_number,
            memberName: $member->full_name,
            accounts: $rows,
            count: $accounts->count(),
            totalShares: (int) $summary['total_shares'],
            totalShareValue: (float) $summary['total_share_value'],
        ))->toArray();
    }
}