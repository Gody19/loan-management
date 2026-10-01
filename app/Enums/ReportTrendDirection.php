<?php

namespace App\Enums;

/**
 * Direction of a deterministic period comparison (Phase 12.0). Computed by the
 * reporting service from two authoritative period values; never by the AI.
 */
enum ReportTrendDirection: string
{
    case Up = 'up';

    case Down = 'down';

    case Flat = 'flat';

    /**
     * Used when the previous comparable period has no measurable value (no
     * historical data at all). The reporting service never manufactures a
     * comparison it cannot support.
     */
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Up => 'up',
            self::Down => 'down',
            self::Flat => 'flat',
            self::Unavailable => 'not comparable',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Up => 'success',
            self::Down => 'danger',
            self::Flat => 'secondary',
            self::Unavailable => 'light',
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
