<?php

namespace App\Enums;

/**
 * Severity of a Financial Intelligence finding. Always informational — a
 * finding never grants a user any authority and never mutates business data.
 */
enum FinancialFindingSeverity: string
{
    case Info = 'info';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Low => 'secondary',
            self::Medium => 'warning',
            self::High => 'danger',
            self::Critical => 'dark',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
