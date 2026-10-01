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
            self::PreviousMonth => 'Previous month',
            self::PreviousQuarter => 'Previous quarter',
            self::Custom => 'Custom range',
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
