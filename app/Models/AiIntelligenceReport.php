<?php

namespace App\Models;

use App\Enums\ReportDatumClassification;
use App\Enums\ReportPeriodType;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persisted intelligence report (Phase 12.0): a structured, classified
 * management report assembled from the existing authoritative FinancePro
 * reporting services, the Phase 11.8 predictive outlooks and the Phase 11.9
 * proactive insights.
 *
 * A report is a read-only analytical artifact. Every financial figure in
 * report_data is computed deterministically by the reporting service; the
 * optional narrative is AI-generated explanation over that dataset and never
 * replaces a figure. A report never modifies a business record, never makes a
 * decision and never alters a prediction or an insight.
 */
class AiIntelligenceReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'branch_id',
        'report_type', 'status',
        'period_type', 'period_start', 'period_end',
        'previous_period_start', 'previous_period_end',
        'data_through', 'generated_at', 'requested_by',
        'report_data', 'narrative', 'ai_generated', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'status' => ReportStatus::class,
            'period_type' => ReportPeriodType::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'previous_period_start' => 'date',
            'previous_period_end' => 'date',
            'data_through' => 'datetime',
            'generated_at' => 'datetime',
            'report_data' => 'array',
            'narrative' => 'array',
            'ai_generated' => 'boolean',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForOrganizations(Builder $query, array $organizationIds): Builder
    {
        return $query->whereIn('organization_id', $organizationIds);
    }

    /**
     * Only a completed report is a report: a failed or still-generating row is
     * never returned as a successful financial report.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Completed->value);
    }

    /**
     * Every datum in the dataset carries its own classification; this helper is
     * the single place a consumer flattens them, so the four categories can
     * never be merged or re-labelled downstream.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dataByClassification(ReportDatumClassification $classification): array
    {
        $rows = [];

        foreach ((array) ($this->report_data['sections'] ?? []) as $section) {
            foreach ((array) ($section[$classification->groupKey()] ?? []) as $datum) {
                $rows[] = $datum + ['section' => $section['key'] ?? null];
            }
        }

        return $rows;
    }

    /**
     * Presentation helper: every datum always shows its explicit label, never
     * a bare number.
     */
    public function classificationLabel(ReportDatumClassification $classification): string
    {
        return $classification->label();
    }
}
