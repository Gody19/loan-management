<?php

namespace App\Enums;

enum LoanRepaymentStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Rejected = 'rejected';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Posted => 'Posted',
            self::Rejected => 'Rejected',
            self::Reversed => 'Reversed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Posted => 'success',
            self::Rejected => 'secondary',
            self::Reversed => 'danger',
        };
    }

    public function isReversible(): bool
    {
        return $this === self::Posted;
    }

    public function isAwaitingReview(): bool
    {
        return $this === self::Pending;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
