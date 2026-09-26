<?php

namespace App\Enums;

/**
 * Lifecycle of a persisted Financial Intelligence anomaly finding:
 * Detected → Reviewed. Findings are never auto-resolved; a human with the
 * ai.anomaly.view capability records the review. Their state is analytical
 * (a review trail), never authoritative financial data.
 */
enum AiAnomalyFindingStatus: string
{
    case Detected = 'detected';
    case Reviewed = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::Detected => 'Detected',
            self::Reviewed => 'Reviewed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Detected => 'warning',
            self::Reviewed => 'success',
        };
    }

    /**
     * A fresh detection is open for human review; a reviewed finding is
     * terminal for the detection trail.
     */
    public function isOpen(): bool
    {
        return $this === self::Detected;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
