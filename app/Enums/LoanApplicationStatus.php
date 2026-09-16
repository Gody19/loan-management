<?php

namespace App\Enums;

enum LoanApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Submitted => 'info',
            self::UnderReview => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'dark',
        };
    }

    public function canTransitionTo(LoanApplicationStatus $newStatus): bool
    {
        return match ($this) {
            self::Draft => in_array($newStatus, [self::Submitted, self::Cancelled]),
            self::Submitted => in_array($newStatus, [self::UnderReview, self::Cancelled]),
            self::UnderReview => in_array($newStatus, [self::Approved, self::Rejected, self::Cancelled]),
            self::Approved => false,
            self::Rejected => false,
            self::Cancelled => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
