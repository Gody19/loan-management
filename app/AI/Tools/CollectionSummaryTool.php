<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\FinancialIntelligence\Services\CollectionIntelligenceService;
use App\Models\User;

/**
 * ai.collection.view — collection position (due vs collected, collection rate,
 * reversal activity) for the acting user's organizations/branches.
 */
class CollectionSummaryTool implements AiToolInterface
{
    public function __construct(
        private readonly CollectionIntelligenceService $collections,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        return $this->collections
            ->summarize($context->organizationIds, $context->branchIds)
            ->toArray();
    }
}
