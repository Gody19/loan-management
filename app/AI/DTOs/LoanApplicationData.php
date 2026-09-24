<?php

namespace App\AI\DTOs;

/**
 * Loan application presentation. Financial values and the eligibility snapshot
 * are stored authoritative data presented verbatim (never recomputed). The
 * snapshot is rendered from the stored eligibility_snapshot array — the same
 * authoritative record the application process wrote at check time.
 */
final class LoanApplicationData
{
    /**
     * @param  array<string, mixed>|null  $eligibility
     */
    public function __construct(
        public readonly string $applicationNumber,
        public readonly string $status,
        public readonly string $memberNumber,
        public readonly string $memberName,
        public readonly ?string $planName,
        public readonly float $requestedAmount,
        public readonly int $requestedTerm,
        public readonly ?string $repaymentFrequency,
        public readonly ?string $loanPurpose,
        public readonly ?string $purposeDescription,
        public readonly ?string $applicationDate,
        public readonly ?string $submittedAt,
        public readonly ?array $eligibility,
    ) {}

    public function toArray(): array
    {
        return [
            'application_number' => $this->applicationNumber,
            'status' => $this->status,
            'member_number' => $this->memberNumber,
            'member_name' => $this->memberName,
            'plan_name' => $this->planName,
            'requested_amount' => $this->requestedAmount,
            'requested_term' => $this->requestedTerm,
            'repayment_frequency' => $this->repaymentFrequency,
            'loan_purpose' => $this->loanPurpose,
            'purpose_description' => $this->purposeDescription,
            'application_date' => $this->applicationDate,
            'submitted_at' => $this->submittedAt,
            'eligibility' => $this->eligibility,
        ];
    }
}