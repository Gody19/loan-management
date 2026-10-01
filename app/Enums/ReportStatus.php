<?php

namespace App\Enums;

/**
 * Generation status of a persisted intelligence report (Phase 12.0). Only a
 * `completed` report is ever presented as a report; a `failed` report carries
 * its diagnostic reason and never masquerades as a successful financial report.
 */
enum ReportStatus: string
{
    case Generating = 'generating';

    case Completed = 'completed';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Generating => 'Generating',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Generating => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    /**
     * Only a completed report may be read as an authoritative report, exported,
     * or injected into an AI provider.
     */
    public function isReportable(): bool
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
