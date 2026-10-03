<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\FinancialIntelligence\Services\AccountingStatementService;
use App\Models\User;

/**
 * ai.accounting.income_statement — the full income statement for the acting
 * user's organizations, delegated to the existing FinancePro
 * IncomeStatementService. Computes nothing of its own.
 */
class IncomeStatementTool implements AiToolInterface
{
    public function __construct(
        private readonly AccountingStatementService $statements,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        return $this->statements->incomeStatement(
            $context->organizationIds,
            $arguments['from'] ?? null,
            $arguments['to'] ?? null,
        );
    }
}
