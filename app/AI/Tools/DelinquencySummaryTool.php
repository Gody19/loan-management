<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\FinancialIntelligence\Services\DelinquencyIntelligenceService;
use App\AI\FinancialIntelligence\Services\ParIntelligenceService;
use App\Models\User;

/**
 * ai.delinquency.view — portfolio-at-risk plus the delinquency profile of the
 * acting user's organizations/branches. Combines the PAR aging buckets and the
 * delinquent-loan list; both figures come from authoritative loan services.
 */
class DelinquencySummaryTool implements AiToolInterface
{
    public function __construct(
        private readonly ParIntelligenceService $par,
        private readonly DelinquencyIntelligenceService $delinquency,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        return [
            'par' => $this->par
                ->summarize($context->organizationIds, $context->branchIds)
                ->toArray(),
            'delinquency_profile' => $this->delinquency
                ->profile($context->organizationIds, $context->branchIds)
                ->toArray(),
        ];
    }
}
