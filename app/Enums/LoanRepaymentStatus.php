<?php

namespace App\Enums;

enum LoanRepaymentStatus: string
{
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Posted => 'Posted',
            self::Reversed => 'Reversed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Posted => 'success',
            self::Reversed => 'danger',
        };
    }

    public function isReversible(): bool
    {
        return $this === self::Posted;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
