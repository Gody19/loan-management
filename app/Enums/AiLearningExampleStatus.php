<?php

namespace App\Enums;

enum AiLearningExampleStatus: string
{
    case Active = 'active';
    case Superseded = 'superseded';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Superseded => 'Superseded',
            self::Revoked => 'Revoked',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Superseded => 'secondary',
            self::Revoked => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
