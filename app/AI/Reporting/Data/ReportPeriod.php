<?php

namespace App\AI\Reporting\Data;

use Carbon\Carbon;

/**
 * A resolved reporting period (Phase 12.0).
 *
 * The period engine is deterministic and is the single place a period becomes
 * a date range. `previous` carries the comparable period used for every trend
 * comparison in the report, so "current vs previous" is resolved once and can
 * never drift between sections.
 */
final class ReportPeriod
{
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly string $start,
        public readonly string $end,
        public readonly ?string $previousStart = null,
        public readonly ?string $previousEnd = null,
    ) {}

    public function hasPrevious(): bool
    {
        return $this->previousStart !== null && $this->previousEnd !== null;
    }

    public function lengthInDays(): int
    {
        return (int) (Carbon::parse($this->start)
            ->diffInDays(Carbon::parse($this->end)) + 1);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'start' => $this->start,
            'end' => $this->end,
            'previous_start' => $this->previousStart,
            'previous_end' => $this->previousEnd,
            'length_days' => $this->lengthInDays(),
        ];
    }
}
