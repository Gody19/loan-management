<?php

namespace App\Enums;

/**
 * The outcome of one scheduled report execution (Phase 12.1).
 *
 * The run row is the durable, idempotent record of an execution: its unique
 * execution key is what stops a schedule from ever producing two reports (or
 * two notification rounds) for the same schedule and period. A failed run keeps
 * a safe, non-secret diagnostic and never reports a financial figure; a skipped
 * run is a claim that lost a race or was deliberately suppressed, so no report
 * is produced and no notification is sent.
 */
enum ReportScheduleRunStatus: string
{
    case Running = 'running';

    case Completed = 'completed';

    case Failed = 'failed';

    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Skipped => 'secondary',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Completed;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
