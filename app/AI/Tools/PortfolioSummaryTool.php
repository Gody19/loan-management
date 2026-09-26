<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\FinancialIntelligence\Services\PortfolioIntelligenceService;
use App\Models\User;

/**
 * ai.portfolio.view — portfolio composition of the acting user's own
 * organizations/branches. No arguments are accepted: the tenant scope is
 * derived exclusively from the trusted context.
 */
class PortfolioSummaryTool implements AiToolInterface
{
    public function __construct(
        private readonly PortfolioIntelligenceService $portfolio,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        return $this->portfolio
            ->summarize($context->organizationIds, $context->branchIds)
            ->toArray();
    }
}
