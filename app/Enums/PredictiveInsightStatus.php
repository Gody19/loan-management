<?php

namespace App\Enums;

/**
 * Lifecycle of a persisted predictive insight (Phase 11.8). A prediction is
 * generated once per (organization, type, method, data snapshot) — re-running
 * with identical inputs never duplicates it (idempotency), a newer snapshot
 * supersedes the previous current one, and a result that sits unused long
 * enough may be refreshed as stale. All states are analytical; none of them
 * can ever influence a financial record.
 */
enum PredictiveInsightStatus: string
{
    case Generated = 'generated';
    case InsufficientData = 'insufficient_data';
    case Failed = 'failed';
    case Stale = 'stale';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Generated => 'Generated',
            self::InsufficientData => 'Insufficient data',
            self::Failed => 'Failed',
            self::Stale => 'Stale',
            self::Superseded => 'Superseded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Generated => 'success',
            self::InsufficientData => 'warning',
            self::Failed => 'danger',
            self::Stale => 'warning',
            self::Superseded => 'secondary',
        };
    }

    public function isCurrent(): bool
    {
        return $this === self::Generated || $this === self::Stale;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
