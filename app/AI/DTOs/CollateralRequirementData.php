<?php

namespace App\AI\DTOs;

/**
 * Collateral requirement passed through from
 * CollateralRequirementService::getRequirement. Plan configuration values are
 * presented as-is; the AI never invents collateral requirements.
 */
final class CollateralRequirementData
{
    /**
     * @param  array<int, string>  $allowedTypes
     * @param  array<int, string>  $requiredDocuments
     */
    public function __construct(
        public readonly int $planId,
        public readonly string $planName,
        public readonly float $requestedAmount,
        public readonly bool $required,
        public readonly float $minimumValue,
        public readonly float $coveragePercentage,
        public readonly array $allowedTypes,
        public readonly array $requiredDocuments,
        public readonly int $minimumAssets,
        public readonly int $maximumAssets,
        public readonly ?string $ruleDescription,
        public readonly string $source = 'CollateralRequirementService',
    ) {}

    public function toArray(): array
    {
        return [
            'plan_id' => $this->planId,
            'plan_name' => $this->planName,
            'requested_amount' => $this->requestedAmount,
            'required' => $this->required,
            'minimum_value' => $this->minimumValue,
            'coverage_percentage' => $this->coveragePercentage,
            'allowed_types' => $this->allowedTypes,
            'required_documents' => $this->requiredDocuments,
            'minimum_assets' => $this->minimumAssets,
            'maximum_assets' => $this->maximumAssets,
            'rule_description' => $this->ruleDescription,
            'source' => $this->source,
        ];
    }
}