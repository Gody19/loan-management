<?php

namespace App\AI\PredictiveIntelligence;

use App\Enums\LoanRepaymentStatus;
use App\Enums\PredictiveDataQuality;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Data-sufficiency gate for predictive intelligence (Phase 11.8).
 *
 * Every baseline is fed exclusively by COMPLETE monthly buckets: the
 * in-progress month is never included in a series (an incomplete period must
 * not leak into a forecast), and no future-dated activity is ever read. The
 * grade is a pure function of the number of complete observations seen, so
 * "not enough data yet" is reported honestly instead of guessing.
 */
final class DataQualityService
{
    /**
     * Return the keys of the trailing $count COMPLETE months, oldest first.
     * The current (in-progress) month is always excluded.
     *
     * @return string[] 'Y-m' keys
     */
    public function completePeriodKeys(int $count): array
    {
        $today = CarbonImmutable::today();

        $keys = [];

        for ($offset = $count; $offset >= 1; $offset--) {
            $keys[] = $today->subMonths($offset)->format('Y-m');
        }

        return $keys;
    }

    /**
     * The in-progress month key (excluded from every completed series).
     */
    public function incompletePeriodKey(): string
    {
        return CarbonImmutable::today()->format('Y-m');
    }

    /**
     * Grade a series by the number of complete observations it produced.
     */
    public function grade(int $observationCount): PredictiveDataQuality
    {
        $minimum = max(1, (int) config('predictive-intelligence.minimum_history_periods', 3));
        $good = max($minimum, (int) config('predictive-intelligence.good_history_periods', 6));

        if ($observationCount >= $good) {
            return PredictiveDataQuality::Good;
        }

        if ($observationCount >= $minimum) {
            return PredictiveDataQuality::Limited;
        }

        return PredictiveDataQuality::Insufficient;
    }

    /**
     * Drop the oldest series rows that carry no activity in the given fields.
     * A window whose records start mid-history must not be read as a long run
     * of zero trend before the first observation; trailing rows (the newest
     * complete months) are always preserved.
     *
     * @param  array<int, array<string, mixed>>  $series
     * @param  string[]  $activityFields
     * @return array<int, array<string, mixed>>
     */
    public function trimLeadingInactive(array $series, array $activityFields): array
    {
        foreach ($series as $index => $row) {
            foreach ($activityFields as $field) {
                if ((float) ($row[$field] ?? 0) != 0.0) {
                    return array_values(array_slice($series, $index));
                }
            }
        }

        return [];
    }

    /**
     * The earliest month-end boundary for a window of complete periods — used
     * to bound the source queries (inclusive start).
     */
    public function windowStart(CarbonInterface $today, int $count): CarbonImmutable
    {
        return CarbonImmutable::parse($today)->startOfMonth()->subMonths($count);
    }

    /**
     * First day of the source window a prediction is computed from (inclusive):
     * the start of the oldest complete month feeding the baseline.
     */
    public function dataFrom(CarbonInterface $today, int $count): string
    {
        return $this->windowStart($today, $count)->toDateString();
    }

    /**
     * Detect organizational data-quality problems that would undermine a
     * baseline. Each check is bounded to the acting organization's own records
     * (optionally narrowed by branch), returns a short code interpreted at the
     * edges as warnings, and never edits source data — it only reports.
     *
     * @param  int[]  $branchIds
     * @return array<string, string> issue code => human-readable description
     */
    public function issues(int $organizationId, array $branchIds = []): array
    {
        $issues = [];

        $duplicates = LoanRepayment::where('organization_id', $organizationId)
            ->where('status', LoanRepaymentStatus::Posted)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->groupBy('loan_id', 'payment_date', 'amount')
            ->having('count', '>', 1)
            ->selectRaw('COUNT(*) AS count')
            ->get()
            ->count();

        if ($duplicates > 0) {
            $issues['duplicate_repayments'] = "{$duplicates} posted repayment(s) share the same loan, date and amount (likely duplicated entries).";
        }

        $nonPositive = LoanRepayment::where('organization_id', $organizationId)
            ->where('status', LoanRepaymentStatus::Posted)
            ->where('amount', '<=', 0)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->count();

        if ($nonPositive > 0) {
            $issues['non_positive_repayment_amounts'] = "{$nonPositive} posted repayment(s) have a non-positive amount.";
        }

        $loansMissingMember = Loan::where('organization_id', $organizationId)
            ->whereNull('member_id')
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->count();

        if ($loansMissingMember > 0) {
            $issues['loans_missing_member'] = "{$loansMissingMember} loan(s) in scope have no member relationship.";
        }

        $schedulesMissingLoan = LoanRepaymentSchedule::where('organization_id', $organizationId)
            ->whereNull('loan_id')
            ->count();

        if ($schedulesMissingLoan > 0) {
            $issues['schedules_missing_loan'] = "{$schedulesMissingLoan} repayment schedule(s) reference no loan.";
        }

        return $issues;
    }

    /**
     * The latest point through which past activity may be considered: the end
     * of the last complete month is the input boundary, but today's date is
     * the snapshot date of the insight.
     */
    public function snapshotDate(): string
    {
        return CarbonImmutable::today()->toDateString();
    }
}
