<?php

namespace App\Models;

use App\AI\Reporting\Data\ReportPeriod;
use App\AI\Reporting\Services\ReportPeriodService;
use App\Enums\ReportScheduleFrequency;
use App\Enums\ReportScheduleRecipientMode;
use App\Enums\ReportType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring management intelligence report (Phase 12.1).
 *
 * A schedule is configuration, not intelligence: it stores a closed frequency,
 * the Phase 12.0 report type to produce, the tenant scope it is authorized for,
 * the audience mode and the deterministic next-run moment. It holds no
 * financial figure, and producing the report is always the work of the Phase
 * 12.0 reporting service over a completed period.
 *
 * The schedule is an advisory, read-only distribution mechanism: it never
 * modifies a business record, never makes a decision and never widens the
 * authority of the user who created it.
 */
class AiReportSchedule extends Model
{
    use HasFactory;

    protected $table = 'ai_report_schedules';

    protected $fillable = [
        'organization_id', 'branch_id',
        'name', 'report_type', 'frequency',
        'run_time', 'weekday', 'day_of_month', 'timezone',
        'recipient_mode', 'recipients', 'include_narrative',
        'is_active', 'next_run_at', 'last_run_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'frequency' => ReportScheduleFrequency::class,
            'recipient_mode' => ReportScheduleRecipientMode::class,
            'recipients' => 'array',
            'include_narrative' => 'boolean',
            'is_active' => 'boolean',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AiReportScheduleRun::class, 'ai_report_schedule_id')->latest('id');
    }

    public function scopeForOrganizations(Builder $query, array $organizationIds): Builder
    {
        return $query->whereIn('organization_id', $organizationIds);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }

    /**
     * Whether the schedule is branch-scoped, which narrows both the report and
     * its recipient set.
     */
    public function isBranchScoped(): bool
    {
        return $this->branch_id !== null;
    }

    /**
     * The completed reporting period this schedule reports on. Always resolved
     * through the Phase 12.0 period engine for the schedule's own timezone, so
     * a scheduled report never covers a partially elapsed window.
     */
    public function period(?CarbonImmutable $asOf = null): ReportPeriod
    {
        return app(ReportPeriodService::class)->resolve(
            $this->frequency->periodType()->value,
            null,
            null,
            ($asOf ?? CarbonImmutable::now($this->timezone))->setTimezone($this->timezone),
        );
    }
}
