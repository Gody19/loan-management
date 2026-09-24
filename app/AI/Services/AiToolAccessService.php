<?php

namespace App\AI\Services;

use App\AI\DTOs\AiContextData;
use App\AI\Exceptions\AiToolException;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\User;
use App\Policies\LoanApplicationPolicy;
use App\Policies\LoanPlanPolicy;
use App\Policies\LoanPolicy;
use App\Policies\MemberPolicy;

/**
 * Trusted resolution of business records for AI tools.
 *
 * The model may supply business identifiers (member_number, loan_number,
 * application_number, loan_plan_id) but they are resolved ONLY inside the
 * acting user's authorized scope. Tenant identifiers (organization_id,
 * branch_id, member_id, ...) are never accepted as arguments — they always
 * come from the trusted AiContextData.
 *
 * VICOBA Members are held to the strictest scope: owner-only. Staff scope is
 * delegated to the existing FinancePro policies so this service never
 * re-implements (or loosens) established authorization rules.
 */
class AiToolAccessService
{
    public function isOwnerOnly(AiContextData $context): bool
    {
        return $context->hasRole('VICOBA Member');
    }

    /**
     * Resolve a member the acting user is allowed to see.
     *
     * @throws AiToolException
     */
    public function resolveMember(User $user, AiContextData $context, ?string $memberNumber = null): Member
    {
        if ($this->isOwnerOnly($context)) {
            if ($context->memberId === null) {
                $this->notFound();
            }

            $member = Member::withTrashed()->find($context->memberId);

            if (! $member) {
                $this->notFound();
            }

            if ($memberNumber !== null && $memberNumber !== $member->member_number) {
                $this->notFound();
            }

            return $member;
        }

        $memberNumber = trim((string) $memberNumber);

        if ($memberNumber === '') {
            throw new AiToolException('validation_failed');
        }

        $member = Member::query()
            ->where('member_number', $memberNumber)
            ->when(! $context->isSuperAdmin, function ($query) use ($context) {
                $query->whereIn('organization_id', $context->organizationIds);
            })
            ->first();

        if (! $member || ! app(MemberPolicy::class)->view($user, $member)) {
            $this->notFound();
        }

        return $member;
    }

    /**
     * Resolve a loan the acting user is allowed to see.
     *
     * @throws AiToolException
     */
    public function resolveLoan(User $user, AiContextData $context, string $loanNumber): Loan
    {
        $loanNumber = trim($loanNumber);

        if ($loanNumber === '') {
            throw new AiToolException('validation_failed');
        }

        $loan = Loan::query()
            ->where('loan_number', $loanNumber)
            ->when(! $context->isSuperAdmin, function ($query) use ($context) {
                $query->whereIn('organization_id', $context->organizationIds);
            })
            ->first();

        if (! $loan) {
            $this->notFound();
        }

        if ($this->isOwnerOnly($context)) {
            if ((int) $loan->member_id !== (int) $context->memberId) {
                $this->notFound();
            }

            return $loan;
        }

        if (! app(LoanPolicy::class)->view($user, $loan)) {
            $this->notFound();
        }

        return $loan;
    }

    /**
     * Resolve a loan application the acting user is allowed to see.
     *
     * @throws AiToolException
     */
    public function resolveLoanApplication(User $user, AiContextData $context, string $applicationNumber): LoanApplication
    {
        $applicationNumber = trim($applicationNumber);

        if ($applicationNumber === '') {
            throw new AiToolException('validation_failed');
        }

        $application = LoanApplication::query()
            ->where('application_number', $applicationNumber)
            ->when(! $context->isSuperAdmin, function ($query) use ($context) {
                $query->whereIn('organization_id', $context->organizationIds);
            })
            ->first();

        if (! $application) {
            $this->notFound();
        }

        if ($this->isOwnerOnly($context)) {
            if ((int) $application->member_id !== (int) $context->memberId) {
                $this->notFound();
            }

            return $application;
        }

        if (! app(LoanApplicationPolicy::class)->view($user, $application)) {
            $this->notFound();
        }

        return $application;
    }

    /**
     * Resolve a loan plan the acting user is allowed to reference.
     *
     * @throws AiToolException
     */
    public function resolveLoanPlan(User $user, AiContextData $context, int $loanPlanId): LoanPlan
    {
        $plan = LoanPlan::query()
            ->where('id', $loanPlanId)
            ->when(! $context->isSuperAdmin, function ($query) use ($context) {
                $query->whereIn('organization_id', $context->organizationIds);
            })
            ->first();

        if (! $plan || ! app(LoanPlanPolicy::class)->view($user, $plan)) {
            $this->notFound();
        }

        return $plan;
    }

    /**
     * Guard a date-range argument pair used to bound statement queries.
     * Empty strings and invalid dates are rejected cleanly.
     *
     * @return array{from: ?string, to: ?string}
     *
     * @throws AiToolException
     */
    public function validateDateRange(?string $from, ?string $to): array
    {
        $from = $from !== null ? trim($from) : '';
        $to = $to !== null ? trim($to) : '';

        if ($from !== '' && ! strtotime($from)) {
            throw new AiToolException('validation_failed');
        }

        if ($to !== '' && ! strtotime($to)) {
            throw new AiToolException('validation_failed');
        }

        return [
            'from' => $from !== '' ? $from : null,
            'to' => $to !== '' ? $to : null,
        ];
    }

    /**
     * Clamp a requested result count to a safe server-side bound.
     */
    public function boundedLimit(mixed $requested, int $maximum, int $default = 20): int
    {
        if ($requested === null || $requested === '') {
            return $default;
        }

        $value = (int) $requested;

        return max(1, min($maximum, $value));
    }

    protected function notFound(): never
    {
        throw new AiToolException('not_found');
    }
}