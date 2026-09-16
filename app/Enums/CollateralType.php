<?php

namespace App\Enums;

enum CollateralType: string
{
    case Land = 'land';
    case Vehicle = 'vehicle';
    case Equipment = 'equipment';
    case Building = 'building';
    case Jewelry = 'jewelry';
    case Savings = 'savings';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Land => 'Land',
            self::Vehicle => 'Vehicle',
            self::Equipment => 'Equipment',
            self::Building => 'Building',
            self::Jewelry => 'Jewelry',
            self::Savings => 'Savings',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
