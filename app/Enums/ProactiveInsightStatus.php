<?php

namespace App\Enums;

/**
 * Lifecycle of a proactive insight (Phase 11.9).
 *
 *   new            generated, not yet seen
 *   read           seen by the operator (dashboard lists marked as read)
 *   acknowledged   operator took notice of the condition
 *   resolved       operator confirms the condition is handled
 *   dismissed      operator intentionally ignores this insight
 *   expired        domain state no longer holds and the insight was retired
 *
 * Resolution and reuse are always driven by domain state or an explicit human
 * decision — never by the AI model.
 */
enum ProactiveInsightStatus: string
{
    case New = 'new';

    case Read = 'read';

    case Acknowledged = 'acknowledged';

    case Resolved = 'resolved';

    case Dismissed = 'dismissed';

    case Expired = 'expired';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'primary',
            self::Read => 'info',
            self::Acknowledged => 'secondary',
            self::Resolved => 'success',
            self::Dismissed => 'secondary',
            self::Expired => 'secondary',
        };
    }

    /**
     * States still surfaced in the open insight list.
     */
    public function isOpen(): bool
    {
        return in_array($this, [
            self::New,
            self::Read,
            self::Acknowledged,
        ], true);
    }
}
