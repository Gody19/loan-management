<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\FinancialIntelligence\Services\AccountingStatementService;
use App\Models\User;

/**
 * ai.accounting.trial_balance — the trial balance for the acting user's
 * organizations, delegated to the existing FinancePro TrialBalanceService,
 * including the debit/credit integrity check. Computes nothing of its own.
 */
class TrialBalanceTool implements AiToolInterface
{
    public function __construct(
        private readonly AccountingStatementService $statements,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        return $this->statements->trialBalance(
            $context->organizationIds,
            $arguments['from'] ?? null,
            $arguments['to'] ?? null,
        );
    }
}
