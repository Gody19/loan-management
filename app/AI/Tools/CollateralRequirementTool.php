<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\CollateralRequirementData;
use App\AI\Services\AiToolAccessService;
use App\Models\User;
use App\Services\CollateralRequirementService;

/**
 * ai.collateral.requirement.check — authoritative collateral requirement from
 * CollateralRequirementService::getRequirement for a plan + amount. Plan
 * configuration values are presented as-is; nothing is invented.
 */
class CollateralRequirementTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
        private readonly CollateralRequirementService $collateral,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        if (! isset($arguments['loan_plan_id']) || ! array_key_exists('requested_amount', $arguments)) {
            throw new \App\AI\Exceptions\AiToolException('validation_failed');
        }

        $plan = $this->access->resolveLoanPlan($user, $context, (int) $arguments['loan_plan_id']);

        $requestedAmount = (float) $arguments['requested_amount'];

        if ($requestedAmount <= 0) {
            throw new \App\AI\Exceptions\AiToolException('validation_failed');
        }

        $requirement = $this->collateral->getRequirement($plan, $requestedAmount);

        $rule = $requirement['rule'] ?? null;

        return (new CollateralRequirementData(
            planId: (int) $plan->id,
            planName: $plan->name,
            requestedAmount: $requestedAmount,
            required: (bool) $requirement['required'],
            minimumValue: (float) $requirement['minimum_value'],
            coveragePercentage: (float) $requirement['coverage_percentage'],
            allowedTypes: array_values($requirement['allowed_types'] ?? []),
            requiredDocuments: array_values($requirement['required_documents'] ?? []),
            minimumAssets: (int) $requirement['minimum_assets'],
            maximumAssets: (int) $requirement['maximum_assets'],
            ruleDescription: $rule?->description,
        ))->toArray();
    }
}