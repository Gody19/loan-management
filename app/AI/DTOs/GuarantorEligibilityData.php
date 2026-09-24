<?php

namespace App\AI\DTOs;

/**
 * Guarantor eligibility result passed through from
 * GuarantorEligibilityService::getEligibility. The stored reasoning is
 * presented but never exposes the NIDA number itself.
 */
final class GuarantorEligibilityData
{
    public function __construct(
        public readonly string $memberNumber,
        public readonly string $memberName,
        public readonly bool $eligible,
        public readonly ?string $reason,
        public readonly int $activeCount,
        public readonly int $completedCount,
        public readonly ?string $applicationNumber,
        public readonly string $source = 'GuarantorEligibilityService',
    ) {}

    public function toArray(): array
    {
        return [
            'member_number' => $this->memberNumber,
            'member_name' => $this->memberName,
            'eligible' => $this->eligible,
            'reason' => $this->reason,
            'active_guarantees_count' => $this->activeCount,
            'completed_guarantees_count' => $this->completedCount,
            'checked_against_application_number' => $this->applicationNumber,
            'source' => $this->source,
        ];
    }
}