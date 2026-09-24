<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\SavingsSummaryData;
use App\AI\Services\AiToolAccessService;
use App\Models\User;
use App\Services\FinancialStatementService;

/**
 * ai.member.savings_summary — savings account balances of a member from stored
 * authoritative balances. The total uses the authoritative aggregate so the
 * AI explanation always matches FinancialStatementService.
 */
class SavingsSummaryTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
        private readonly FinancialStatementService $statements,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $member = $this->access->resolveMember($user, $context, $arguments['member_number'] ?? null);

        $accounts = $member->savingsAccounts()->get()->take(50)->values();

        $rows = $accounts->map(function ($account) {
            return [
                'account_number' => $account->account_number,
                'status' => $account->status?->value,
                'current_balance' => (float) $account->current_balance,
            ];
        })->all();

        $summary = $this->statements->getMemberFinancialSummary($member);

        return (new SavingsSummaryData(
            memberNumber: $member->member_number,
            memberName: $member->full_name,
            accounts: $rows,
            count: $accounts->count(),
            totalSavings: (float) $summary['total_savings'],
        ))->toArray();
    }
}