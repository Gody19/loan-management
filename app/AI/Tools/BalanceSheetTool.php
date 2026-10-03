<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\FinancialIntelligence\Services\AccountingStatementService;
use App\Models\User;

/**
 * ai.accounting.balance_sheet — the full balance sheet for the acting user's
 * organizations, delegated to the existing FinancePro BalanceSheetService.
 * Computes nothing of its own.
 */
class BalanceSheetTool implements AiToolInterface
{
    public function __construct(
        private readonly AccountingStatementService $statements,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        return $this->statements->balanceSheet(
            $context->organizationIds,
            $arguments['as_of'] ?? null,
        );
    }
}
