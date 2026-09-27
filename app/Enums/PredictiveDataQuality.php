<?php

namespace App\Enums;

/**
 * Data-quality grade for a predictive insight (Phase 11.8). The grade reports
 * how much trustworthy completed history the statistical baseline actually saw;
 * it is the single gate between "we cannot forecast yet" and "here is an
 * indication". The thresholds live in config/predictive-intelligence.php.
 */
enum PredictiveDataQuality: string
{
    case Good = 'good';
    case Limited = 'limited';
    case Insufficient = 'insufficient';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Limited => 'Limited',
            self::Insufficient => 'Insufficient',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Good => 'success',
            self::Limited => 'warning',
            self::Insufficient => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
