<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\MemberSummaryData;
use App\AI\Services\AiToolAccessService;
use App\Models\User;

/**
 * ai.member.view — read a member profile summary within the authorized scope.
 * VICOBA Members may only view their own member record (owner-only).
 */
class MemberViewTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $member = $this->access->resolveMember($user, $context, $arguments['member_number'] ?? null);

        return (new MemberSummaryData(
            memberNumber: $member->member_number,
            fullName: $member->full_name,
            status: $member->membership_status?->value ?? 'unknown',
            gender: $member->gender?->value,
            joiningDate: $member->joining_date?->toDateString(),
            region: $member->region,
            district: $member->district,
            ward: $member->ward,
        ))->toArray();
    }
}