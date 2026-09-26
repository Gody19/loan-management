<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\FinancialIntelligence\Services\AccountingIntelligenceService;
use App\Models\User;

/**
 * ai.accounting.view — accounting summary (income statement totals,
 * trial-balance integrity, liquid position) from the authoritative FinancePro
 * report services, for the acting user's organizations.
 */
class AccountingSummaryTool implements AiToolInterface
{
    public function __construct(
        private readonly AccountingIntelligenceService $accounting,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        return $this->accounting
            ->summarize($context->organizationIds)
            ->toArray();
    }
}
