<?php

namespace App\Enums;

enum AiMessageRole: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::System => 'System',
            self::User => 'User',
            self::Assistant => 'Assistant',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}