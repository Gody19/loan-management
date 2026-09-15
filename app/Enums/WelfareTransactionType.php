<?php

namespace App\Enums;

enum WelfareTransactionType: string
{
    case Contribution = 'contribution';
    case Benefit = 'benefit';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Contribution => 'Contribution',
            self::Benefit => 'Benefit',
            self::Adjustment => 'Adjustment',
            self::Reversal => 'Reversal',
        };
    }

    public function isCredit(): bool
    {
        return in_array($this, [self::Contribution, self::Adjustment]);
    }

    public function isDebit(): bool
    {
        return in_array($this, [self::Benefit, self::Reversal]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
