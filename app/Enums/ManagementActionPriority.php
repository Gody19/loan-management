<?php

namespace App\Enums;

/**
 * Human-set priority of a management action (Phase 12.3).
 *
 * Priority is always chosen by the person who creates or edits the action. It
 * is never derived from an AI insight, a prediction or any automated severity,
 * so an advisory can never silently promote itself into an urgent task.
 */
enum ManagementActionPriority: string
{
    case Low = 'low';

    case Medium = 'medium';

    case High = 'high';

    case Urgent = 'urgent';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'secondary',
            self::Medium => 'info',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }

    /**
     * Ordering weight (higher first).
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
