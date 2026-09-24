<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\LoanApplicationData;
use App\AI\Services\AiToolAccessService;
use App\Models\User;

/**
 * ai.loan.application.view — loan application within the authorized scope.
 * Financial values and the eligibility snapshot are stored authoritative data
 * presented verbatim (never recomputed).
 */
class LoanApplicationViewTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $application = $this->access->resolveLoanApplication(
            $user,
            $context,
            (string) ($arguments['application_number'] ?? ''),
        );

        return (new LoanApplicationData(
            applicationNumber: $application->application_number,
            status: $application->status?->value ?? 'unknown',
            memberNumber: $application->member?->member_number,
            memberName: $application->member?->full_name,
            planName: $application->loanPlan?->name,
            requestedAmount: (float) $application->requested_amount,
            requestedTerm: (int) $application->requested_term,
            repaymentFrequency: $application->repayment_frequency?->value,
            loanPurpose: $application->loan_purpose?->value,
            purposeDescription: $application->purpose_description,
            applicationDate: $application->application_date?->toDateString(),
            submittedAt: $application->submitted_at?->toISOString(),
            eligibility: $this->presentEligibilitySnapshot($application),
        ))->toArray();
    }

    private function presentEligibilitySnapshot($application): ?array
    {
        $snapshot = $application->eligibility_snapshot;

        if (! is_array($snapshot) || $snapshot === []) {
            return null;
        }

        return [
            'checked_at' => $application->eligibility_checked_at?->toISOString(),
            'eligible' => $snapshot['eligible'] ?? null,
            'requested_amount' => isset($snapshot['requested_amount'])
                ? (float) $snapshot['requested_amount']
                : null,
            'approved_amount' => isset($snapshot['approved_amount'])
                ? (float) $snapshot['approved_amount']
                : null,
            'active_loan_count' => $snapshot['active_loan_count'] ?? null,
            'checks' => $snapshot['checks'] ?? null,
            'failure_reasons' => $snapshot['failure_reasons'] ?? null,
        ];
    }
}