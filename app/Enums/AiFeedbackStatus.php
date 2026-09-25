<?php

namespace App\Enums;

enum AiFeedbackStatus: string
{
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Reviewed = 'reviewed';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::InReview => 'In Review',
            self::Reviewed => 'Reviewed',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::InReview => 'warning',
            self::Reviewed => 'success',
            self::Withdrawn => 'secondary',
        };
    }

    /**
     * Only a submitted or in-review record may still be acted upon. A withdrawn
     * or reviewed record is terminal and must not silently return to the queue.
     */
    public function isOpen(): bool
    {
        return $this === self::Submitted || $this === self::InReview;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
