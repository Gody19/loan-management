<?php

namespace App\Enums;

/**
 * Severity of a proactive insight (Phase 11.9). The levels are advisory only
 * and drive ordering and notification emphasis, never any automated action:
 *
 *   info      neutral observation; no specific action required, monitored
 *   notice    minor signal worth keeping on the radar
 *   warning   attention needed; likely within the action horizon
 *   critical  urgent attention expected before the position deteriorates
 */
enum ProactiveInsightSeverity: string
{
    case Info = 'info';

    case Notice = 'notice';

    case Warning = 'warning';

    case Critical = 'critical';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Bootstrap badge color for the severity.
     */
    public function color(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Notice => 'secondary',
            self::Warning => 'warning',
            self::Critical => 'danger',
        };
    }

    /**
     * Numeric priority used to order an insight list (higher first).
     */
    public function priority(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Notice => 2,
            self::Warning => 3,
            self::Critical => 4,
        };
    }
}
