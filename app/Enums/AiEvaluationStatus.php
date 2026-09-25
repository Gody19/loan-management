<?php

namespace App\Enums;

enum AiEvaluationStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::InReview => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    /**
     * Terminal states may not transition again. This is what makes a second
     * concurrent approve/reject a no-op instead of a second decision.
     */
    public function isTerminal(): bool
    {
        return $this === self::Approved || $this === self::Rejected;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
