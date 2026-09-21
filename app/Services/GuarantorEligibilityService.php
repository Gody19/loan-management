<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanApplicationGuarantor;
use App\Models\Member;

class GuarantorEligibilityService
{
    /**
     * Check if a member can guarantee a loan application.
     *
     * @return array{eligible: bool, reason: ?string}
     */
    public function canGuarantee(Member $member, ?LoanApplication $currentApplication = null): array
    {
        if ($member->membership_status->value !== 'active') {
            return ['eligible' => false, 'reason' => 'Member is not an active member.'];
        }

        if (empty($member->national_id) || trim($member->national_id) === '') {
            return ['eligible' => false, 'reason' => 'Member does not have a valid NIDA number on file.'];
        }

        if ($currentApplication) {
            if ($member->id === $currentApplication->member_id) {
                return ['eligible' => false, 'reason' => 'A member cannot guarantee their own loan application.'];
            }

            if ($member->organization_id !== $currentApplication->organization_id) {
                return ['eligible' => false, 'reason' => 'Guarantor must belong to the same organization.'];
            }

            $alreadyOnApplication = LoanApplicationGuarantor::where('loan_application_id', $currentApplication->id)
                ->where('guarantor_member_id', $member->id)
                ->where('status', '!=', \App\Enums\GuarantorStatus::Rejected)
                ->exists();

            if ($alreadyOnApplication) {
                return ['eligible' => false, 'reason' => 'This member is already a guarantor on this application.'];
            }
        }

        $activeGuarantee = LoanApplicationGuarantor::where('guarantor_member_id', $member->id)
            ->whereIn('status', [\App\Enums\GuarantorStatus::Pending, \App\Enums\GuarantorStatus::Accepted])
            ->whereHas('application', function ($q) {
                $q->whereHas('loan', function ($lq) {
                    $lq->whereNotIn('status', [\App\Enums\LoanStatus::Completed, \App\Enums\LoanStatus::Cancelled]);
                });
            })
            ->exists();

        if ($activeGuarantee) {
            return ['eligible' => false, 'reason' => 'This member is already guaranteeing an active loan that has not been fully repaid.'];
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * Get detailed eligibility information for a member.
     */
    public function getEligibility(Member $member, ?LoanApplication $currentApplication = null): array
    {
        $result = $this->canGuarantee($member, $currentApplication);

        $activeGuarantees = LoanApplicationGuarantor::where('guarantor_member_id', $member->id)
            ->whereIn('status', [\App\Enums\GuarantorStatus::Pending, \App\Enums\GuarantorStatus::Accepted])
            ->with(['application.loan', 'application.member'])
            ->get();

        $completedGuarantees = LoanApplicationGuarantor::where('guarantor_member_id', $member->id)
            ->where('status', \App\Enums\GuarantorStatus::Accepted)
            ->whereHas('application.loan', function ($q) {
                $q->whereIn('status', [\App\Enums\LoanStatus::Completed, \App\Enums\LoanStatus::Cancelled]);
            })
            ->with(['application.loan', 'application.member'])
            ->get();

        return [
            'eligible' => $result['eligible'],
            'reason' => $result['reason'],
            'active_guarantees' => $activeGuarantees,
            'completed_guarantees' => $completedGuarantees,
            'active_count' => $activeGuarantees->count(),
            'completed_count' => $completedGuarantees->count(),
        ];
    }

    /**
     * Check if a member is eligible to guarantee (boolean shorthand).
     */
    public function isEligible(Member $member, ?LoanApplication $currentApplication = null): bool
    {
        return $this->canGuarantee($member, $currentApplication)['eligible'];
    }
}
