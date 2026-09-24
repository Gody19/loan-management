<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\WelfareSummaryData;
use App\AI\Services\AiToolAccessService;
use App\Models\User;
use App\Services\FinancialStatementService;

/**
 * ai.member.welfare_summary — welfare account balances of a member; the
 * aggregate mirrors the authoritative FinancialStatementService figure.
 */
class WelfareSummaryTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
        private readonly FinancialStatementService $statements,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $member = $this->access->resolveMember($user, $context, $arguments['member_number'] ?? null);

        $accounts = $member->welfareAccounts()->get()->take(50)->values();

        $rows = $accounts->map(function ($account) {
            return [
                'account_number' => $account->account_number,
                'status' => $account->status?->value,
                'current_balance' => (float) $account->current_balance,
            ];
        })->all();

        $summary = $this->statements->getMemberFinancialSummary($member);

        return (new WelfareSummaryData(
            memberNumber: $member->member_number,
            memberName: $member->full_name,
            accounts: $rows,
            count: $accounts->count(),
            totalBalance: (float) $summary['welfare_balance'],
        ))->toArray();
    }
}