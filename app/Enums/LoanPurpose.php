<?php

namespace App\Enums;

enum LoanPurpose: string
{
    case Business = 'business';
    case Agriculture = 'agriculture';
    case Education = 'education';
    case Emergency = 'emergency';
    case Personal = 'personal';
    case Development = 'development';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Business',
            self::Agriculture => 'Agriculture',
            self::Education => 'Education',
            self::Emergency => 'Emergency',
            self::Personal => 'Personal',
            self::Development => 'Development',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
