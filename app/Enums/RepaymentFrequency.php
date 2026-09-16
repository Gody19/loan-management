<?php

namespace App\Enums;

enum RepaymentFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::Biweekly => 'Biweekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
        };
    }

    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Weekly => 52,
            self::Biweekly => 26,
            self::Monthly => 12,
            self::Quarterly => 4,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
