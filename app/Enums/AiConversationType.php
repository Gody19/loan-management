<?php

namespace App\Enums;

enum AiConversationType: string
{
    case Private = 'private';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Public => 'Public',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
