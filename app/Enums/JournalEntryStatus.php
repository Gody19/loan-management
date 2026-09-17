<?php

namespace App\Enums;

enum JournalEntryStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
            self::Reversed => 'Reversed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Posted => 'success',
            self::Reversed => 'danger',
        };
    }

    public function isPostable(): bool
    {
        return $this === self::Draft;
    }

    public function isReversible(): bool
    {
        return $this === self::Posted;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
