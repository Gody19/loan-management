<?php

namespace App\AI\PredictiveIntelligence\Services;

use App\AI\PredictiveIntelligence\DataQualityService;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Delinquency risk indicator (Phase 11.8, §24).
 *
 * A cohort-level 0-100 advisory indicator built from the authorized active
 * loan book: share of loans currently past due, share of settled installments
 * that were paid late, depth of delay and recurrence of lateness. Everything
 * is recomputed from authoritative schedule records at read time (never from
 * any stored "risk" column) and no member-level detail ever leaves the
 * service — the output is aggregate staff-facing signal used to prioritize
 * collection follow-up, not to decide a member's fate.
 */
class DelinquencyRiskService
{
    public function __construct(
        private readonly DataQualityService $quality,
    ) {}

    /**
     * @param  int[]  $branchIds
     * @return array<string, mixed>
     */
    public function predict(int $organizationId, array $branchIds = [], int $horizon = 3): array
    {
        $today = CarbonImmutable::today();
        $currency = (string) config('predictive-intelligence.currency', 'TZS');
        $minimum = max(1, (int) config('predictive-intelligence.minimum_history_periods', 3));

        $loans = $this->activeLoans($organizationId, $branchIds);

        $loansTotal = $loans->count();

        if ($loansTotal < $minimum) {
            return [
                'status' => 'insufficient_data',
                'method' => 'naive',
                'target_period' => $today->addMonth()->format('Y-m'),
                'data_through' => $today->toDateString(),
                'horizon' => 1,
                'confidence' => 'low',
                'data_quality' => 'insufficient',
                'observation_count' => $loansTotal,
                'value_total' => null,
                'currency' => $currency,
                'series' => [],
                'factors' => [
                    "No risk indicator possible for organization {$organizationId}: {$loansTotal} active loan(s) in scope (at least {$minimum} required).",
                ],
                'assumptions' => [
                    'insufficient_reason' => 'active_loans_below_minimum',
                ],
                'explanation' => 'Insufficient active loans in scope to compute a cohort delinquency-risk indicator.',
            ];
        }

        $overdueLoans = 0;
        $everLateLoans = 0;
        $paidCount = 0;
        $paidLateCount = 0;
        $dpds = [];
        $overduePrincipal = 0.0;
        $totalOutstanding = 0.0;

        foreach ($loans as $loan) {
            $totalOutstanding += (float) $loan->outstanding_balance;

            $schedules = $loan->repaymentSchedule;

            $loanOverdue = false;
            $loanLateBefore = false;

            foreach ($schedules as $schedule) {
                $due = $schedule->due_date;

                if ($due === null) {
                    continue;
                }

                $isPaid = $schedule->status === LoanScheduleInstallmentStatus::Paid;

                if ($isPaid && $schedule->paid_date !== null && $schedule->paid_date->lte($today)
                    && $schedule->paid_date->gt($due)) {
                    $paidLateCount++;
                    $paidCount++;
                    $loanLateBefore = true;
                } elseif ($isPaid) {
                    $paidCount++;
                }

                if (! $isPaid && (float) $schedule->outstanding_amount > 0 && $due->lt($today)) {
                    $loanOverdue = true;
                    $loanLateBefore = true;
                    $overduePrincipal += (float) $schedule->outstanding_amount;
                    $dpds[] = (int) abs($today->startOfDay()->diffInDays($due->startOfDay()));
                }
            }

            if ($loanOverdue) {
                $overdueLoans++;
            }

            if ($loanLateBefore) {
                $everLateLoans++;
            }
        }

        $loansTotal = max(1, $loansTotal);
        $overdueRatio = $overdueLoans / $loansTotal;
        $latePaymentRatio = $paidCount > 0 ? $paidLateCount / $paidCount : 0;
        $recurringRatio = $everLateLoans / $loansTotal;
        $averageDpd = $dpds === [] ? 0 : array_sum($dpds) / count($dpds);
        $severity = min(1.0, $averageDpd / 90.0);

        $weights = [0.40, 0.25, 0.15, 0.20]; // overdue share, late-payments share, recurrence, depth
        $score = round(100 * ($overdueRatio * $weights[0] + $latePaymentRatio * $weights[1] + $recurringRatio * $weights[2] + $severity * $weights[3]), 2);

        $mediumThreshold = (float) config('predictive-intelligence.delinquency_risk_levels.medium_threshold', 34);
        $highThreshold = (float) config('predictive-intelligence.delinquency_risk_levels.high_threshold', 67);

        if ($score >= $highThreshold) {
            $level = 'high';
        } elseif ($score >= $mediumThreshold) {
            $level = 'medium';
        } else {
            $level = 'low';
        }

        $grade = $this->quality->grade($loansTotal);
        $confidence = $loansTotal >= (int) config('predictive-intelligence.good_history_periods', 6) ? 'medium' : 'low';

        $factors = [
            "Composite risk indicator: {$score} / 100 (level {$level}).",
            "{$overdueLoans} of {$loansTotal} active loans (".number_format($overdueRatio * 100, 1).'%) are currently past due.',
            'Average days past due among overdue loans: '.number_format($averageDpd, 1).' day(s).',
            "{$paidLateCount} of {$paidCount} settled installment(s) were paid late (".number_format($latePaymentRatio * 100, 1).'%).',
            "{$everLateLoans} of {$loansTotal} loans (".number_format($recurringRatio * 100, 1).'%) have a history of lateness or are past due now.',
        ];

        return [
            'status' => 'generated',
            'method' => 'composite',
            'target_period' => $today->addMonth()->format('Y-m'),
            'data_through' => $today->toDateString(),
            'horizon' => 1,
            'confidence' => $confidence,
            'data_quality' => $grade->value,
            'observation_count' => $loansTotal,
            'value_total' => $score,
            'currency' => $currency,
            'series' => [],
            'factors' => $factors,
            'assumptions' => [
                'weights' => $weights,
                'level_bounds' => ['medium_from' => $mediumThreshold, 'high_from' => $highThreshold],
                'scope' => 'aggregate cohort level only; no member-level detail computed or exposed.',
                'data_source' => 'Authoritative repayment schedules and loan balances recomputed at read time.',
            ],
            'explanation' => "Cohort delinquency-risk indicator of {$score} / 100 (level {$level}) across {$loansTotal} active loans in scope.",
        ];
    }

    /**
     * @param  int[]  $branchIds
     */
    protected function activeLoans(int $organizationId, array $branchIds): Collection
    {
        $query = Loan::where('organization_id', $organizationId)
            ->where('status', LoanStatus::Active)
            ->with('repaymentSchedule');

        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        return $query->get();
    }
}
