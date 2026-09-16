<?php

namespace App\Enums;

enum InterestMethod: string
{
    case Flat = 'flat';
    case ReducingBalance = 'reducing_balance';

    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Flat',
            self::ReducingBalance => 'Reducing Balance',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
