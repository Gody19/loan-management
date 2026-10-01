<?php

namespace App\AI\Reporting\Services;

use App\AI\Reporting\Data\ReportPeriod;
use App\Enums\ReportPeriodType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The Phase 12.0 period engine.
 *
 * Resolves a controlled reporting period into a deterministic date range plus
 * its previous comparable period, and refuses anything unreasonable rather
 * than silently clamping: an unknown period, an inverted custom range and an
 * over-long custom range are all validation errors. Keeping this in one place
 * guarantees every section of every report is measured over exactly the same
 * window.
 */
class ReportPeriodService
{
    /**
     * @throws InvalidArgumentException on an unknown period or invalid range
     */
    public function resolve(
        string $type,
        ?string $from = null,
        ?string $to = null,
        ?CarbonImmutable $today = null,
    ): ReportPeriod {
        $periodType = ReportPeriodType::tryFrom($type);

        if ($periodType === null) {
            throw new InvalidArgumentException('Unsupported reporting period.');
        }

        $today = ($today ?? CarbonImmutable::now())->startOfDay();

        return match ($periodType) {
            ReportPeriodType::Today => new ReportPeriod(
                type: $periodType->value,
                label: $periodType->label(),
                start: $today->toDateString(),
                end: $today->toDateString(),
                previousStart: $today->subDay()->toDateString(),
                previousEnd: $today->subDay()->toDateString(),
            ),
            ReportPeriodType::ThisWeek => new ReportPeriod(
                type: $periodType->value,
                label: $periodType->label(),
                start: $today->startOfWeek()->toDateString(),
                end: $today->endOfWeek()->toDateString(),
                previousStart: $today->startOfWeek()->subWeek()->toDateString(),
                previousEnd: $today->startOfWeek()->subWeek()->endOfWeek()->toDateString(),
            ),
            ReportPeriodType::ThisMonth => new ReportPeriod(
                type: $periodType->value,
                label: $periodType->label(),
                start: $today->startOfMonth()->toDateString(),
                end: $today->endOfMonth()->toDateString(),
                previousStart: $today->startOfMonth()->subMonth()->toDateString(),
                previousEnd: $today->startOfMonth()->subMonth()->endOfMonth()->toDateString(),
            ),
            ReportPeriodType::ThisQuarter => new ReportPeriod(
                type: $periodType->value,
                label: $periodType->label(),
                start: $today->firstOfQuarter()->toDateString(),
                end: $today->lastOfQuarter()->toDateString(),
                previousStart: $today->firstOfQuarter()->subQuarter()->toDateString(),
                previousEnd: $today->firstOfQuarter()->subQuarter()->lastOfQuarter()->toDateString(),
            ),
            ReportPeriodType::ThisYear => new ReportPeriod(
                type: $periodType->value,
                label: $periodType->label(),
                start: $today->startOfYear()->toDateString(),
                end: $today->endOfYear()->toDateString(),
                previousStart: $today->startOfYear()->subYear()->toDateString(),
                previousEnd: $today->startOfYear()->subYear()->endOfYear()->toDateString(),
            ),
            ReportPeriodType::PreviousMonth => new ReportPeriod(
                type: $periodType->value,
                label: $periodType->label(),
                start: $today->startOfMonth()->subMonth()->toDateString(),
                end: $today->startOfMonth()->subMonth()->endOfMonth()->toDateString(),
                previousStart: $today->startOfMonth()->subMonths(2)->toDateString(),
                previousEnd: $today->startOfMonth()->subMonths(2)->endOfMonth()->toDateString(),
            ),
            ReportPeriodType::PreviousQuarter => new ReportPeriod(
                type: $periodType->value,
                label: $periodType->label(),
                start: $today->firstOfQuarter()->subQuarter()->toDateString(),
                end: $today->firstOfQuarter()->subQuarter()->lastOfQuarter()->toDateString(),
                previousStart: $today->firstOfQuarter()->subQuarters(2)->toDateString(),
                previousEnd: $today->firstOfQuarter()->subQuarters(2)->lastOfQuarter()->toDateString(),
            ),
            ReportPeriodType::Custom => $this->custom($periodType, $from, $to),
        };
    }

    /**
     * A custom range must be explicit, ordered and bounded. from <= to is
     * enforced, and the span may not exceed the configured maximum.
     */
    protected function custom(ReportPeriodType $type, ?string $from, ?string $to): ReportPeriod
    {
        if ($from === null || $to === null || $from === '' || $to === '') {
            throw new InvalidArgumentException('A custom reporting period requires a start and end date.');
        }

        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('The reporting period start date must not be after its end date.');
        }

        $days = (int) $start->diffInDays($end) + 1;
        $maximum = (int) config('intelligence-reporting.max_custom_range_days', 366);

        if ($days > $maximum) {
            throw new InvalidArgumentException('The reporting period exceeds the maximum supported range.');
        }

        // The immediately preceding window of equal length is the comparable
        // period, so a custom range is compared like any other.
        $previousEnd = $start->subDay();
        $previousStart = $previousEnd->subDays($days - 1);

        return new ReportPeriod(
            type: $type->value,
            label: $type->label(),
            start: $start->toDateString(),
            end: $end->toDateString(),
            previousStart: $previousStart->toDateString(),
            previousEnd: $previousEnd->toDateString(),
        );
    }
}
