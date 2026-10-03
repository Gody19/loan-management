<?php

namespace App\AI\ManagementActions\Data;

/**
 * A safe period-over-period comparison of one management action measurement
 * (Phase 12.4).
 *
 * The single rule this object enforces is that a change is never manufactured.
 * When the previous period has no comparable figure the comparison is
 * explicitly `unavailable`, and when the previous figure exists but is zero the
 * percentage change stays null because dividing by zero has no honest answer.
 * An absolute change is still reported in that case, because subtracting zero
 * from a count is a fact.
 *
 * `direction` is one of:
 *   up           the current figure is higher
 *   down         the current figure is lower
 *   flat         the figure is unchanged
 *   unavailable  there is nothing to compare against
 */
final class ManagementActionComparison
{
    public const DIRECTION_UP = 'up';

    public const DIRECTION_DOWN = 'down';

    public const DIRECTION_FLAT = 'flat';

    public const DIRECTION_UNAVAILABLE = 'unavailable';

    public function __construct(
        public readonly bool $available,
        public readonly mixed $current,
        public readonly mixed $previous,
        public readonly ?float $change,
        public readonly ?float $percentChange,
        public readonly string $direction,
        public readonly ?string $unavailableReason = null,
    ) {}

    /**
     * A comparison that cannot be made, with the reason stated honestly.
     */
    public static function unavailable(string $reason, mixed $current = null): self
    {
        return new self(
            available: false,
            current: $current,
            previous: null,
            change: null,
            percentChange: null,
            direction: self::DIRECTION_UNAVAILABLE,
            unavailableReason: $reason,
        );
    }

    /**
     * Compare two counts. `null` for the previous period means the period could
     * not be resolved at all.
     */
    public static function forCount(?int $current, ?int $previous): self
    {
        if ($current === null || $previous === null) {
            return self::unavailable('The previous period has no comparable data.');
        }

        $change = $current - $previous;

        return new self(
            available: true,
            current: $current,
            previous: $previous,
            change: (float) $change,
            percentChange: $previous !== 0
                ? round((float) $change / abs((float) $previous) * 100, 1)
                : null,
            direction: self::directionFor((float) $change),
        );
    }

    /**
     * Compare two rates. `change` is the difference in percentage points, which
     * is the honest unit for a rate; the relative percentage change is only
     * offered when the previous rate was above zero.
     */
    public static function forRate(?float $current, ?float $previous): self
    {
        if ($current === null) {
            return self::unavailable('No eligible actions in the selected period.');
        }

        if ($previous === null) {
            return self::unavailable('The previous period has no comparable data.');
        }

        $change = round($current - $previous, 1);

        return new self(
            available: true,
            current: round($current, 1),
            previous: round($previous, 1),
            change: $change,
            percentChange: $previous > 0
                ? round($change / $previous * 100, 1)
                : null,
            direction: self::directionFor($change),
        );
    }

    protected static function directionFor(float $change): string
    {
        return match (true) {
            $change > 0 => self::DIRECTION_UP,
            $change < 0 => self::DIRECTION_DOWN,
            default => self::DIRECTION_FLAT,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'current' => $this->current,
            'previous' => $this->previous,
            'change' => $this->change,
            'percent_change' => $this->percentChange,
            'direction' => $this->direction,
            'unavailable_reason' => $this->unavailableReason,
        ];
    }
}
