<?php

namespace App\Models;

use App\Enums\ReportPeriodType;
use App\Enums\ReportScheduleRunStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One execution of a recurring management report (Phase 12.1).
 *
 * The run row is the durable idempotency record: its unique execution key
 * (schedule + resolved period) is claimed before any financial work happens, so
 * a schedule can never produce two reports — or notify its audience twice — for
 * the same reporting window, no matter how many times the scheduler fires or how
 * many processes race.
 *
 * A run stores only execution metadata. Every figure lives in the linked Phase
 * 12.0 report; a failed or skipped run carries a safe diagnostic and never
 * masquerades as a produced report.
 */
class AiReportScheduleRun extends Model
{
    use HasFactory;

    protected $table = 'ai_report_schedule_runs';

    /**
     * Transient, never persisted: set when an execution request found an
     * existing run for the same schedule and period instead of creating one. It
     * lets an idempotent manual run be reported honestly as "already executed"
     * rather than as a fresh generation.
     */
    public bool $replayed = false;

    protected $fillable = [
        'ai_report_schedule_id', 'ai_intelligence_report_id',
        'execution_key', 'trigger', 'status',
        'period_type', 'period_start', 'period_end', 'timezone',
        'notifications_sent', 'digest_available', 'failure_reason',
        'requested_by', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportScheduleRunStatus::class,
            'period_type' => ReportPeriodType::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'notifications_sent' => 'integer',
            'digest_available' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AiReportSchedule::class, 'ai_report_schedule_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AiIntelligenceReport::class, 'ai_intelligence_report_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopeForSchedule(Builder $query, int $scheduleId): Builder
    {
        return $query->where('ai_report_schedule_id', $scheduleId);
    }

    /**
     * The idempotency key for a schedule and a resolved period. Kept as one
     * deterministic function so the scheduler, the manual run and the unique
     * index can never disagree about what "the same run" means.
     */
    public static function executionKeyFor(int $scheduleId, string $periodType, string $periodStart, string $periodEnd): string
    {
        return sprintf('%d|%s|%s|%s', $scheduleId, $periodType, $periodStart, $periodEnd);
    }

    /**
     * Whether the run produced a report that may be presented as a report.
     */
    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful() && $this->ai_intelligence_report_id !== null;
    }
}
