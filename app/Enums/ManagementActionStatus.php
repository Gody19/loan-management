<?php

namespace App\Enums;

/**
 * Lifecycle of a management action (Phase 12.3).
 *
 *   open         created and awaiting a human to begin work
 *   in_progress  a human has started the follow-up
 *   completed    a human has recorded the follow-up as done (terminal)
 *   cancelled    a human has decided the follow-up is no longer needed (terminal)
 *
 * A management action is a human workflow record. No AI model and no automated
 * process ever moves it between states; every transition is an explicit,
 * audited human act. `completed` and `cancelled` are terminal and immutable.
 */
enum ManagementActionStatus: string
{
    case Open = 'open';

    case InProgress = 'in_progress';

    case Completed = 'completed';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'secondary',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'dark',
        };
    }

    /**
     * Whether the action still needs attention (drives the Action Center).
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::InProgress], true);
    }

    /**
     * A terminal action is immutable: it can never be moved again.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * The only permitted transitions. A completed or cancelled action can never
     * be reopened, and a completed action can never be cancelled after the fact.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Open => in_array($target, [self::InProgress, self::Completed, self::Cancelled], true),
            self::InProgress => in_array($target, [self::Completed, self::Cancelled], true),
            self::Completed, self::Cancelled => false,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
