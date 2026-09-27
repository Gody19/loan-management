<?php

namespace App\Enums;

/**
 * Confidence attached to a predictive insight (Phase 11.8). It derives purely
 * from the depth and consistency of the underlying historical data — never
 * from any model certainty or subjective judgment — and is purely advisory.
 */
enum PredictionConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::High => 'success',
            self::Medium => 'warning',
            self::Low => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
