<?php

namespace App\Enums;

enum AiFeedbackType: string
{
    case Positive = 'positive';
    case Negative = 'negative';
    case Correction = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::Positive => 'Helpful',
            self::Negative => 'Not helpful',
            self::Correction => 'Correction',
        };
    }

    public function requiresCorrection(): bool
    {
        return $this === self::Correction;
    }

    public function color(): string
    {
        return match ($this) {
            self::Positive => 'success',
            self::Negative => 'danger',
            self::Correction => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
