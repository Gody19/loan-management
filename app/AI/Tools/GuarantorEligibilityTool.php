<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\GuarantorEligibilityData;
use App\AI\Services\AiToolAccessService;
use App\Models\User;
use App\Services\GuarantorEligibilityService;

/**
 * ai.guarantor.eligibility.check — authoritative guarantor eligibility from
 * GuarantorEligibilityService. The candidate member is resolved within the
 * acting user's scope; VICOBA Members may only check themselves. The NIDA
 * number itself is never exposed by the result.
 */
class GuarantorEligibilityTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
        private readonly GuarantorEligibilityService $guarantorEligibility,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $candidate = $this->access->resolveMember($user, $context, (string) ($arguments['member_number'] ?? ''));

        $application = null;

        if (! empty($arguments['application_number'])) {
            $application = $this->access->resolveLoanApplication(
                $user,
                $context,
                (string) $arguments['application_number'],
            );
        }

        $result = $this->guarantorEligibility->getEligibility($candidate, $application);

        return (new GuarantorEligibilityData(
            memberNumber: $candidate->member_number,
            memberName: $candidate->full_name,
            eligible: (bool) $result['eligible'],
            reason: $result['reason'],
            activeCount: (int) $result['active_count'],
            completedCount: (int) $result['completed_count'],
            applicationNumber: $application?->application_number,
        ))->toArray();
    }
}