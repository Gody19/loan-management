<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * How often a management report is scheduled (Phase 12.1).
 *
 * The frequency is a closed, controlled vocabulary: a schedule can never carry
 * a user-supplied cron expression, so the platform decides what a schedule may
 * mean and only the time of day, the weekday and the day of the month are ever
 * configurable.
 *
 * Every frequency resolves to exactly one *completed* reporting period through
 * the existing Phase 12.0 period engine — never a partially elapsed one — so a
 * scheduled report is always measured over a settled window.
 */
enum ReportScheduleFrequency: string
{
    case Daily = 'daily';

    case Weekly = 'weekly';

    case Monthly = 'monthly';

    case Quarterly = 'quarterly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Daily => 'Once a day, reporting the previous completed day.',
            self::Weekly => 'Once a week on a chosen weekday, reporting the previous completed week.',
            self::Monthly => 'Once a month on a chosen day, reporting the previous completed month.',
            self::Quarterly => 'Once a quarter on a chosen day, reporting the previous completed quarter.',
        };
    }

    /**
     * The reporting period this frequency covers. Always a completed period
     * from the Phase 12.0 engine, so scheduled reporting can never present an
     * in-progress window as a settled report.
     */
    public function periodType(): ReportPeriodType
    {
        return match ($this) {
            self::Daily => ReportPeriodType::Yesterday,
            self::Weekly => ReportPeriodType::PreviousWeek,
            self::Monthly => ReportPeriodType::PreviousMonth,
            self::Quarterly => ReportPeriodType::PreviousQuarter,
        };
    }

    /**
     * Whether the frequency needs a weekday (ISO-8601, 1 = Monday .. 7 = Sunday).
     */
    public function requiresWeekday(): bool
    {
        return $this === self::Weekly;
    }

    /**
     * Whether the frequency needs a day of the month (1..31).
     */
    public function requiresDayOfMonth(): bool
    {
        return $this === self::Monthly || $this === self::Quarterly;
    }

    /**
     * The first run strictly after the given moment, in the schedule's own
     * timezone.
     *
     * The calculation is deterministic and always moves forward, so a schedule
     * can never be pinned to the past and re-running the scheduler can never
     * replay a window. A day of month that does not exist in the target month
     * (the 31st in April, for example) is clamped to that month's last day
     * rather than skipped, so a monthly schedule never silently loses a run.
     *
     * @param  string  $time  A `H:i[:s]` time of day in the schedule timezone.
     */
    public function nextRunAfter(CarbonImmutable $after, string $time, ?int $weekday = null, ?int $dayOfMonth = null): CarbonImmutable
    {
        $time = $this->normalizeTime($time);

        if ($this->requiresWeekday() && ($weekday === null || $weekday < 1 || $weekday > 7)) {
            throw new InvalidArgumentException('A weekly schedule requires a weekday between 1 and 7.');
        }

        if ($this->requiresDayOfMonth() && ($dayOfMonth === null || $dayOfMonth < 1 || $dayOfMonth > 31)) {
            throw new InvalidArgumentException('A monthly or quarterly schedule requires a day of the month between 1 and 31.');
        }

        $parts = explode(':', $time);
        $hour = (int) $parts[0];
        $minute = (int) $parts[1];
        $second = isset($parts[2]) ? (int) $parts[2] : 0;

        return match ($this) {
            self::Daily => $after
                ->setTime($hour, $minute, $second)
                ->addDay()
                ->startOfDay()
                ->setTime($hour, $minute, $second),

            self::Weekly => $this->nextWeekly($after, $hour, $minute, $second, (int) $weekday),

            self::Monthly => $this->nextMonthLike($after, $hour, $minute, $second, (int) $dayOfMonth, 1),

            self::Quarterly => $this->nextMonthLike($after, $hour, $minute, $second, (int) $dayOfMonth, 3),
        };
    }

    /**
     * The next occurrence of the chosen weekday, always strictly in the future.
     */
    protected function nextWeekly(CarbonImmutable $after, int $hour, int $minute, int $second, int $weekday): CarbonImmutable
    {
        // ISO-8601 weekday of today, then the forward-only offset.
        $current = (int) $after->format('N');
        $offset = ($weekday - $current + 7) % 7;

        $candidate = $after->startOfDay()->addDays($offset)->setTime($hour, $minute, $second);

        if ($candidate->lessThanOrEqualTo($after)) {
            $candidate = $candidate->addWeek();
        }

        return $candidate;
    }

    /**
     * The next occurrence of the chosen day of the month, one or three months
     * ahead, clamped to the target month's real length.
     */
    protected function nextMonthLike(CarbonImmutable $after, int $hour, int $minute, int $second, int $dayOfMonth, int $stepMonths): CarbonImmutable
    {
        // addMonthsNoOverflow is essential: a plain addMonths would roll the 31st
        // of January into March, silently skipping February's run entirely.
        $month = $after->startOfDay()->addMonthsNoOverflow($stepMonths);

        // Clamp the requested day to the month's real length (31 -> 30/28/29).
        $day = min($dayOfMonth, $month->daysInMonth);

        $candidate = $month->day($day)->setTime($hour, $minute, $second);

        if ($candidate->lessThanOrEqualTo($after)) {
            $month = $month->addMonthsNoOverflow($stepMonths);
            $day = min($dayOfMonth, $month->daysInMonth);
            $candidate = $month->day($day)->setTime($hour, $minute, $second);
        }

        return $candidate;
    }

    /**
     * A schedule time is validated rather than trusted, so a malformed value
     * can never reach the scheduler.
     */
    protected function normalizeTime(string $time): string
    {
        if (! preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', trim($time))) {
            throw new InvalidArgumentException('The schedule time must be a valid H:i or H:i:s time of day.');
        }

        $parts = explode(':', trim($time));

        $hour = (int) $parts[0];
        $minute = (int) $parts[1];
        $second = isset($parts[2]) ? (int) $parts[2] : 0;

        if ($hour > 23 || $minute > 59 || $second > 59) {
            throw new InvalidArgumentException('The schedule time must be a valid H:i or H:i:s time of day.');
        }

        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
