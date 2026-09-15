<?php

namespace App\Enums;

enum Relationship: string
{
    case Spouse = 'spouse';
    case Parent = 'parent';
    case Child = 'child';
    case Sibling = 'sibling';
    case Relative = 'relative';
    case Guardian = 'guardian';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spouse => 'Spouse',
            self::Parent => 'Parent',
            self::Child => 'Child',
            self::Sibling => 'Sibling',
            self::Relative => 'Relative',
            self::Guardian => 'Guardian',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
