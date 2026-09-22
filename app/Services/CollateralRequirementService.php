<?php

namespace App\Services;

use App\Enums\LoanCollateralStatus;
use App\Models\LoanApplication;
use App\Models\LoanApplicationCollateral;
use App\Models\LoanApplicationCollateralSnapshot;
use App\Models\LoanPlan;
use App\Models\LoanPlanCollateralRule;
use Illuminate\Support\Facades\DB;

class CollateralRequirementService
{
    /**
     * Resolve the applicable collateral rule for a given loan plan and amount.
     * Returns null if no rule exists (no collateral configured for this amount tier).
     */
    public function resolveRule(LoanPlan $plan, float $requestedAmount): ?LoanPlanCollateralRule
    {
        return LoanPlanCollateralRule::where('loan_plan_id', $plan->id)
            ->active()
            ->forAmount($requestedAmount)
            ->first();
    }

    /**
     * Determine if collateral is required for this plan + amount combination.
     */
    public function isCollateralRequired(LoanPlan $plan, float $requestedAmount): bool
    {
        $rule = $this->resolveRule($plan, $requestedAmount);
        return $rule?->collateral_required ?? false;
    }

    /**
     * Calculate the minimum required collateral value based on the rule and loan amount.
     */
    public function calculateRequiredValue(LoanPlan $plan, float $requestedAmount): float
    {
        $rule = $this->resolveRule($plan, $requestedAmount);
        if (!$rule || !$rule->collateral_required) {
            return 0;
        }
        return $rule->calculateMinimumCollateralValue($requestedAmount);
    }

    /**
     * Get the full requirement details for a plan + amount.
     */
    public function getRequirement(LoanPlan $plan, float $requestedAmount): ?array
    {
        $rule = $this->resolveRule($plan, $requestedAmount);

        if (!$rule || !$rule->collateral_required) {
            return [
                'required' => false,
                'rule' => null,
                'minimum_value' => 0,
                'coverage_percentage' => 0,
                'allowed_types' => [],
                'required_documents' => [],
                'minimum_assets' => 0,
                'maximum_assets' => 0,
            ];
        }

        return [
            'required' => true,
            'rule' => $rule,
            'minimum_value' => $rule->calculateMinimumCollateralValue($requestedAmount),
            'coverage_percentage' => $rule->coverage_percentage,
            'allowed_types' => $rule->allowed_collateral_types ?? [],
            'required_documents' => $rule->required_document_types ?? [],
            'minimum_assets' => $rule->minimum_assets,
            'maximum_assets' => $rule->maximum_assets,
        ];
    }

    /**
     * Validate that submitted collaterals meet the requirement for an application.
     * Returns an array of error messages (empty if valid).
     */
    public function validateCollateral(LoanApplication $application): array
    {
        $errors = [];
        $plan = $application->loanPlan;
        $requestedAmount = (float) $application->requested_amount;

        $rule = $this->resolveRule($plan, $requestedAmount);

        if (!$rule || !$rule->collateral_required) {
            return [];
        }

        $collaterals = $application->collaterals()
            ->where('status', '!=', LoanCollateralStatus::Rejected)
            ->get();

        $assetCount = $collaterals->count();

        if ($assetCount < $rule->minimum_assets) {
            $errors[] = "Minimum {$rule->minimum_assets} collateral asset(s) required. Currently has {$assetCount}.";
        }

        if ($assetCount > $rule->maximum_assets) {
            $errors[] = "Maximum {$rule->maximum_assets} collateral asset(s) allowed. Currently has {$assetCount}.";
        }

        $totalValue = 0;
        foreach ($collaterals as $collateral) {
            if (!$rule->allowsCollateralType($collateral->collateral_type->value)) {
                $errors[] = "Collateral type '{$collateral->collateral_type->label()}' is not allowed for this loan tier.";
            }

            $effectiveValue = $collateral->getEffectiveValue();
            $totalValue += $effectiveValue;

            $documents = $collateral->documents;
            foreach ($rule->required_document_types as $docType) {
                $hasDoc = $documents->contains('document_type', $docType);
                if (!$hasDoc) {
                    $docLabel = \App\Enums\CollateralDocumentType::tryFrom($docType)?->label() ?? $docType;
                    $errors[] = "Required document '{$docLabel}' is missing for collateral '{$collateral->collateral_type->label()}'.";
                }
            }
        }

        $minimumRequired = $rule->calculateMinimumCollateralValue($requestedAmount);
        if ($totalValue < $minimumRequired) {
            $shortfall = $minimumRequired - $totalValue;
            $errors[] = "Insufficient collateral value. Required: TSh " . number_format($minimumRequired, 0) .
                        ", Submitted: TSh " . number_format($totalValue, 0) .
                        ", Shortfall: TSh " . number_format($shortfall, 0) . ".";
        }

        return $errors;
    }

    /**
     * Create an immutable snapshot of the collateral requirement at application submission time.
     */
    public function createSnapshot(LoanApplication $application): LoanApplicationCollateralSnapshot
    {
        $plan = $application->loanPlan;
        $requestedAmount = (float) $application->requested_amount;
        $rule = $this->resolveRule($plan, $requestedAmount);

        return LoanApplicationCollateralSnapshot::create([
            'loan_application_id' => $application->id,
            'loan_plan_id' => $plan->id,
            'collateral_rule_id' => $rule?->id,
            'requested_amount' => $requestedAmount,
            'collateral_required' => $rule?->collateral_required ?? false,
            'coverage_percentage' => $rule?->coverage_percentage ?? 0,
            'minimum_collateral_value' => $rule ? $rule->calculateMinimumCollateralValue($requestedAmount) : 0,
            'minimum_assets' => $rule?->minimum_assets ?? 0,
            'maximum_assets' => $rule?->maximum_assets ?? 0,
            'allowed_collateral_types' => $rule?->allowed_collateral_types,
            'required_document_types' => $rule?->required_document_types,
            'description' => $rule?->description,
            'snapshot_created_at' => now(),
        ]);
    }

    /**
     * Check if all required document types are uploaded for a collateral.
     */
    public function getMissingDocuments(LoanApplicationCollateral $collateral, ?LoanPlanCollateralRule $rule = null): array
    {
        if (!$rule) {
            $application = $collateral->application;
            $rule = $this->resolveRule($application->loanPlan, (float) $application->requested_amount);
        }

        if (!$rule || empty($rule->required_document_types)) {
            return [];
        }

        $uploadedTypes = $collateral->documents->pluck('document_type')->map(fn($v) => $v->value)->toArray();
        return array_diff($rule->required_document_types, $uploadedTypes);
    }
}
