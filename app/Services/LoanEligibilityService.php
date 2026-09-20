<?php

namespace App\Services;

use App\DataTransferObjects\EligibilityCheckResult;
use App\Enums\MemberStatus;
use App\Enums\ShareAccountStatus;
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

        // 5. Active loans limit
        $activeLoanCount = $this->countActiveLoans($member);
        $checks['active_loans_limit'] = $activeLoanCount < $plan->maximum_active_loans ? 'pass' : 'fail';
        if ($checks['active_loans_limit'] === 'fail') {
            $failureReasons[] = 'Member has reached the maximum number of active loans ('.$plan->maximum_active_loans.').';
        }

        // 6. Minimum savings balance
        $totalSavings = $this->getTotalSavings($member);
        $checks['minimum_savings'] = $totalSavings >= $plan->minimum_savings_balance ? 'pass' : 'fail';
        if ($checks['minimum_savings'] === 'fail') {
            $failureReasons[] = 'Minimum savings balance of '.number_format($plan->minimum_savings_balance, 2).' not met. Current: '.number_format($totalSavings, 2).'.';
        }

        // 7. Savings multiplier limit (80% rule applied via maxLoanFromSavings)
        // Skip if minimum_savings_balance is 0 and member has no savings (savings not required)
        if ($totalSavings > 0 || $plan->minimum_savings_balance > 0) {
            $maxBySavings = $totalSavings * $plan->savings_multiplier;
            $maxLoanFromSavings = $this->applyEightyPercentRule($maxBySavings);
            $checks['savings_multiplier'] = $requestedAmount <= $maxLoanFromSavings ? 'pass' : 'fail';
            if ($checks['savings_multiplier'] === 'fail') {
                $failureReasons[] = 'Requested amount exceeds savings-based limit of '.number_format($maxLoanFromSavings, 2).' (savings × '.$plan->savings_multiplier.', capped at 80%).';
            }
        } else {
            $checks['savings_multiplier'] = 'pass';
            $maxLoanFromSavings = 0;
        }

        // 8. Share multiplier limit — only enforced when member has shares
        $totalShares = $this->getTotalShares($member);
        if ($totalShares > 0) {
            $maxByShares = $totalShares * $plan->share_multiplier;
            $maxLoanFromShares = $this->applyEightyPercentRule($maxByShares);
            $checks['share_multiplier'] = $requestedAmount <= $maxLoanFromShares ? 'pass' : 'fail';
            if ($checks['share_multiplier'] === 'fail') {
                $failureReasons[] = 'Requested amount exceeds share-based limit of '.number_format($maxLoanFromShares, 2).' (shares × '.$plan->share_multiplier.', capped at 80%).';
            }
        } else {
            $checks['share_multiplier'] = 'pass';
            $maxLoanFromShares = 0;
        }

        // 9. Loan-to-savings ratio — skip if savings not required (minimum_savings_balance = 0) and no savings
        if ($totalSavings > 0) {
            $ratio = $requestedAmount / $totalSavings;
            $checks['loan_to_savings_ratio'] = $ratio <= $plan->maximum_loan_to_savings_ratio ? 'pass' : 'fail';
            if ($checks['loan_to_savings_ratio'] === 'fail') {
                $failureReasons[] = 'Loan-to-savings ratio of '.number_format($ratio, 2).' exceeds maximum of '.$plan->maximum_loan_to_savings_ratio.'.';
            }
        } elseif ($plan->minimum_savings_balance > 0) {
            $checks['loan_to_savings_ratio'] = 'fail';
            $failureReasons[] = 'Cannot borrow with zero savings balance. Minimum required: '.number_format($plan->minimum_savings_balance, 2).'.';
        } else {
            $checks['loan_to_savings_ratio'] = 'pass';
        }

        $eligible = ! in_array('fail', $checks, true);

        // Approved amount = min(requested, max allowed by savings multiplier)
        $approvedAmount = $eligible ? $requestedAmount : 0;

        return new EligibilityCheckResult(
            memberName: $member->full_name,
            planName: $plan->name,
            requestedAmount: $requestedAmount,
            approvedAmount: $approvedAmount,
            eligible: $eligible,
            checks: $checks,
            failureReasons: $failureReasons,
            totalSavings: $totalSavings,
            totalShares: $totalShares,
            activeLoanCount: $activeLoanCount,
            maxAllowedBySavings: $maxLoanFromSavings,
            maxAllowedByShares: $maxLoanFromShares,
        );
    }

    private function countActiveLoans(Member $member): int
    {
        // Placeholder — will be expanded when loan accounts are created in future phases
        return 0;
    }

    private function getTotalSavings(Member $member): float
    {
        return (float) $member->savingsAccounts()
            ->where('status', 'active')
            ->sum('current_balance');
    }

    private function getTotalShares(Member $member): float
    {
        return (float) $member->shareAccounts()
            ->where('status', ShareAccountStatus::Active)
            ->sum('total_value');
    }

    private function applyEightyPercentRule(float $amount): float
    {
        return round($amount * 0.8, 2);
    }
}
