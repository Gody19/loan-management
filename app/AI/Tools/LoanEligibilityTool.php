<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\LoanEligibilityData;
use App\AI\Services\AiToolAccessService;
use App\Models\User;
use App\Services\LoanEligibilityService;

/**
 * ai.loan.eligibility.check — authoritative eligibility result from
 * LoanEligibilityService::checkEligibility. The AI explains the result; it
 * cannot introduce or remove rules (in particular, no savings-multiplier
 * logic is applied here or anywhere in the AI path).
 */
class LoanEligibilityTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
        private readonly LoanEligibilityService $eligibility,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        if (! isset($arguments['loan_plan_id']) || ! array_key_exists('requested_amount', $arguments)) {
            throw new \App\AI\Exceptions\AiToolException('validation_failed');
        }

        $member = $this->access->resolveMember($user, $context, $arguments['member_number'] ?? null);

        $plan = $this->access->resolveLoanPlan($user, $context, (int) $arguments['loan_plan_id']);

        $requestedAmount = (float) $arguments['requested_amount'];
        $termMonths = isset($arguments['term_months']) ? (int) $arguments['term_months'] : null;

        if ($requestedAmount <= 0) {
            throw new \App\AI\Exceptions\AiToolException('validation_failed');
        }

        $result = $this->eligibility->checkEligibility($member, $plan, $requestedAmount, $termMonths);

        return (new LoanEligibilityData(
            memberName: $result->memberName,
            planName: $result->planName,
            requestedAmount: $result->requestedAmount,
            approvedAmount: $result->approvedAmount,
            eligible: $result->eligible,
            checks: $result->checks,
            failureReasons: $result->failureReasons,
            activeLoanCount: $result->activeLoanCount,
        ))->toArray();
    }
}