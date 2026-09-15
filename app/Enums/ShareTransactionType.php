<?php

namespace App\Enums;

enum ShareTransactionType: string
{
    case Purchase = 'purchase';
    case Redeem = 'redeem';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Redeem => 'Redeem',
            self::Adjustment => 'Adjustment',
            self::Reversal => 'Reversal',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
