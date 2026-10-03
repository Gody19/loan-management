<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\LoanApplicationStartData;
use App\AI\Services\AiToolAccessService;
use App\Enums\LoanApplicationStatus;
use App\Enums\MemberStatus;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\User;

/**
 * ai.loan.application.start — answers "can I apply for a loan?" without ever
 * inventing an eligibility result.
 *
 * The only gate applied here is the one the application workflow already
 * enforces: MemberLoanApplicationRequest::authorize() requires a linked member
 * record with an active membership status. Everything else about a loan
 * application (eligibility, guarantors, collateral) is plan- and amount-specific
 * and belongs to submission, not to starting, so this capability does not call
 * LoanEligibilityService and never computes a hypothetical amount.
 *
 * Existing draft/submitted/under-review applications are reported as context.
 * FinancePro defines no rule that blocks a new application on that basis — the
 * pending-application check in MemberLoanController only disables a button — so
 * it is surfaced as information and explicitly flagged as non-blocking.
 */
class LoanApplicationStartTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $member = $this->access->resolveMember($user, $context, $arguments['member_number'] ?? null);

        $canStart = $member->membership_status === MemberStatus::Active;

        $blockingReasons = [];

        if (! $canStart) {
            $blockingReasons[] = sprintf(
                'Your membership status is "%s". Only active members can start a loan application.',
                $member->membership_status->value,
            );
        }

        return (new LoanApplicationStartData(
            memberName: (string) $member->full_name,
            memberStatus: $member->membership_status->value,
            canStart: $canStart,
            blockingReasons: $blockingReasons,
            requirementsForEligibility: ['loan_plan', 'requested_amount'],
            availablePlans: $this->availablePlans($member),
            inProgressApplications: $this->inProgressApplications($member),
        ))->toArray();
    }

    /**
     * Active loan plans the member may actually choose from, scoped to the
     * member's own organization. Plan identifiers are intentionally omitted so
     * that a plan can only be selected once the member names it, never because
     * the assistant picked the first row.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function availablePlans(Member $member): array
    {
        return LoanPlan::query()
            ->active()
            ->where('organization_id', $member->organization_id)
            ->orderBy('id')
            ->get()
            ->map(fn (LoanPlan $plan) => [
                'name' => (string) $plan->name,
                'loan_purpose' => $plan->loan_purpose->value,
                'minimum_amount' => (float) $plan->minimum_amount,
                'maximum_amount' => (float) $plan->maximum_amount,
                'minimum_term' => (int) $plan->minimum_term,
                'maximum_term' => (int) $plan->maximum_term,
                'requires_guarantor' => (bool) $plan->requires_guarantor,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function inProgressApplications(Member $member): array
    {
        return $member->loanApplications()
            ->whereIn('status', [
                LoanApplicationStatus::Draft->value,
                LoanApplicationStatus::Submitted->value,
                LoanApplicationStatus::UnderReview->value,
            ])
            ->with('loanPlan')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($application) => [
                'application_number' => (string) $application->application_number,
                'status' => (string) $application->status->value,
                'plan_name' => (string) ($application->loanPlan?->name ?? 'unknown'),
            ])
            ->all();
    }
}
