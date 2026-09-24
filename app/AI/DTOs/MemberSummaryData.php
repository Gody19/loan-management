<?php

namespace App\AI\DTOs;

/**
 * Sanitized, whitelisted member profile summary. PII is deliberately
 * restricted: no national id, phone, email, address line, next-of-kin,
 * documents, or membership number derivation leaks are present.
 */
final class MemberSummaryData
{
    public function __construct(
        public readonly string $memberNumber,
        public readonly string $fullName,
        public readonly string $status,
        public readonly ?string $gender,
        public readonly ?string $joiningDate,
        public readonly ?string $region,
        public readonly ?string $district,
        public readonly ?string $ward,
    ) {}

    public function toArray(): array
    {
        return [
            'member_number' => $this->memberNumber,
            'full_name' => $this->fullName,
            'status' => $this->status,
            'gender' => $this->gender,
            'joining_date' => $this->joiningDate,
            'region' => $this->region,
            'district' => $this->district,
            'ward' => $this->ward,
        ];
    }
}