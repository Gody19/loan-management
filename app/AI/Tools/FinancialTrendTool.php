<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\FinancialIntelligence\Services\FinancialTrendService;
use App\Models\User;

/**
 * ai.trend.view — monthly disbursement/collection trend series for the acting
 * user's organizations/branches.
 */
class FinancialTrendTool implements AiToolInterface
{
    public function __construct(
        private readonly FinancialTrendService $trends,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        return $this->trends
            ->monthly($context->organizationIds, $context->branchIds)
            ->toArray();
    }
}
