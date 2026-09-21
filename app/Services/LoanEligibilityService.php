<?php

namespace App\Services;

use App\DataTransferObjects\EligibilityCheckResult;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Models\LoanPlan;
use App\Models\Member;

class LoanEligibilityService
{
    public function checkEligibility(
        Member $member,
        LoanPlan $plan,
        float $requestedAmount,
        ?int $termMonths = null,
    ): EligibilityCheckResult {
        $checks = [];
        $failureReasons = [];

        // 1. Member must be active
        $checks['member_active'] = $member->membership_status === MemberStatus::Active ? 'pass' : 'fail';
        if ($checks['member_active'] === 'fail') {
            $failureReasons[] = 'Member is not active.';
        }

        // 2. Plan must be active
        $checks['plan_active'] = $plan->status->value === 'active' ? 'pass' : 'fail';
        if ($checks['plan_active'] === 'fail') {
            $failureReasons[] = 'Loan plan is not active.';
        }

        // 3. Amount within range
        $checks['amount_in_range'] = ($requestedAmount >= $plan->minimum_amount && $requestedAmount <= $plan->maximum_amount) ? 'pass' : 'fail';
        if ($checks['amount_in_range'] === 'fail') {
            $failureReasons[] = 'Requested amount is outside the allowed range ('.number_format($plan->minimum_amount, 2).' — '.number_format($plan->maximum_amount, 2).').';
        }

        // 4. Term within range
        if ($termMonths !== null) {
            $checks['term_in_range'] = ($termMonths >= $plan->minimum_term && $termMonths <= $plan->maximum_term) ? 'pass' : 'fail';
            if ($checks['term_in_range'] === 'fail') {
                $failureReasons[] = 'Requested term is outside the allowed range ('.$plan->minimum_term.' — '.$plan->maximum_term.' months).';
            }
        } else {
            $checks['term_in_range'] = 'pass';
        }

        // 5. Active loans — cannot apply if any active loan is not paid by 85%
        $activeLoans = $this->getActiveLoans($member);
        $activeLoanCount = $activeLoans->count();
        $blockedByUnpaid = false;
        $lowestPaidPercent = 100;

        foreach ($activeLoans as $loan) {
            if ($loan->total_amount > 0) {
                $paidPercent = ($loan->amount_paid / $loan->total_amount) * 100;
                if ($paidPercent < $lowestPaidPercent) {
                    $lowestPaidPercent = round($paidPercent, 1);
                }
                if ($paidPercent < 85) {
                    $blockedByUnpaid = true;
                }
            }
        }

        if ($blockedByUnpaid) {
            $checks['active_loans_limit'] = 'fail';
            $failureReasons[] = 'You have an active loan that is only '.$lowestPaidPercent.'% paid. You must pay at least 85% of your current loan before applying for a new one.';
        } elseif ($activeLoanCount >= $plan->maximum_active_loans) {
            $checks['active_loans_limit'] = 'fail';
            $failureReasons[] = 'Member has reached the maximum number of active loans ('.$plan->maximum_active_loans.').';
        } else {
            $checks['active_loans_limit'] = 'pass';
        }

        $eligible = ! in_array('fail', $checks, true);
        $approvedAmount = $eligible ? $requestedAmount : 0;

        return new EligibilityCheckResult(
            memberName: $member->full_name,
            planName: $plan->name,
            requestedAmount: $requestedAmount,
            approvedAmount: $approvedAmount,
            eligible: $eligible,
            checks: $checks,
            failureReasons: $failureReasons,
            activeLoanCount: $activeLoanCount,
        );
    }

    private function getActiveLoans(Member $member): \Illuminate\Support\Collection
    {
        return $member->loans()
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Disbursed])
            ->get();
    }
}
