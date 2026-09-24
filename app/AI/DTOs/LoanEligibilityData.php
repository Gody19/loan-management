<?php

namespace App\AI\DTOs;

/**
 * Authoritative loan eligibility result passed through from
 * LoanEligibilityService::checkEligibility. Fields mirror EligibilityCheckResult
 * exactly; the AI explains the result and never introduces its own rules
 * (in particular, no savings-multiplier logic is added here or elsewhere).
 */
final class LoanEligibilityData
{
    /**
     * @param  array<string, string>  $checks
     * @param  array<int, string>  $failureReasons
     */
    public function __construct(
        public readonly string $memberName,
        public readonly string $planName,
        public readonly float $requestedAmount,
        public readonly float $approvedAmount,
        public readonly bool $eligible,
        public readonly array $checks,
        public readonly array $failureReasons,
        public readonly int $activeLoanCount,
        public readonly string $source = 'LoanEligibilityService',
    ) {}

    public function toArray(): array
    {
        return [
            'member_name' => $this->memberName,
            'plan_name' => $this->planName,
            'requested_amount' => $this->requestedAmount,
            'approved_amount' => $this->approvedAmount,
            'eligible' => $this->eligible,
            'checks' => $this->checks,
            'failure_reasons' => $this->failureReasons,
            'active_loan_count' => $this->activeLoanCount,
            'source' => $this->source,
        ];
    }
}