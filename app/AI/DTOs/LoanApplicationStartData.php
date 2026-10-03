<?php

namespace App\AI\DTOs;

/**
 * Workflow state for the FinancePro loan application process, as returned by
 * ai.loan.application.start.
 *
 * This DTO deliberately carries NO eligibility verdict. A verdict requires a
 * concrete loan plan and a concrete requested amount, and neither may ever be
 * guessed; until both are supplied by the member, the authoritative answer is
 * that eligibility cannot be calculated yet. The available loan plans are
 * returned by name and range so the assistant can ask which plan applies rather
 * than choosing one.
 */
final class LoanApplicationStartData
{
    /**
     * @param  array<int, string>  $blockingReasons
     * @param  array<int, string>  $requirementsForEligibility
     * @param  array<int, array<string, mixed>>  $availablePlans
     * @param  array<int, array<string, string>>  $inProgressApplications
     */
    public function __construct(
        public readonly string $memberName,
        public readonly string $memberStatus,
        public readonly bool $canStart,
        public readonly array $blockingReasons,
        public readonly array $requirementsForEligibility,
        public readonly array $availablePlans,
        public readonly array $inProgressApplications,
        public readonly string $source = 'FinancePro loan application workflow',
    ) {}

    public function toArray(): array
    {
        return [
            'member_name' => $this->memberName,
            'member_status' => $this->memberStatus,
            'can_start' => $this->canStart,
            'blocking_reasons' => $this->blockingReasons,
            'requirements_for_eligibility' => $this->requirementsForEligibility,
            'available_plans' => $this->availablePlans,
            'in_progress_applications' => $this->inProgressApplications,
            'in_progress_applications_block_start' => false,
            'source' => $this->source,
        ];
    }
}
