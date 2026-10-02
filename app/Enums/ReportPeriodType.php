<?php

namespace App\Enums;

/**
 * The reporting period of an intelligence report (Phase 12.0). Every period is
 * resolved deterministically by the period engine into an inclusive
 * start/end date range; `Custom` additionally requires an explicit,
 * validated from/to range.
 */
enum ReportPeriodType: string
{
    case Today = 'today';

    case ThisWeek = 'this_week';

    case ThisMonth = 'this_month';

    case ThisQuarter = 'this_quarter';

    case ThisYear = 'this_year';

    case Yesterday = 'yesterday';

    case PreviousWeek = 'previous_week';

    case PreviousMonth = 'previous_month';

    case PreviousQuarter = 'previous_quarter';

    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::ThisWeek => 'This week',
            self::ThisMonth => 'This month',
            self::ThisQuarter => 'This quarter',
            self::ThisYear => 'This year',
            self::Yesterday => 'Yesterday',
            self::PreviousWeek => 'Previous week',
            self::PreviousMonth => 'Previous month',
            self::PreviousQuarter => 'Previous quarter',
            self::Custom => 'Custom range',
        };
    }

    /**
     * Whether the period describes a window that has already fully elapsed.
     *
     * A scheduled run (Phase 12.1) reports on a completed period only, so it can
     * never present a half-finished day, week, month or quarter as if it were a
     * settled management report. The in-progress periods (today, this_week,
     * this_month, this_quarter, this_year) and the explicit custom range are
     * therefore not completed.
     */
    public function isCompleted(): bool
    {
        return match ($this) {
            self::Yesterday, self::PreviousWeek, self::PreviousMonth, self::PreviousQuarter => true,
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
