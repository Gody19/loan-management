<?php

namespace App\AI\PredictiveIntelligence;

use App\Enums\PredictiveDataQuality;
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
     * The earliest month-end boundary for a window of complete periods — used
     * to bound the source queries (inclusive start).
     */
    public function windowStart(CarbonInterface $today, int $count): CarbonImmutable
    {
        return CarbonImmutable::parse($today)->startOfMonth()->subMonths($count);
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
