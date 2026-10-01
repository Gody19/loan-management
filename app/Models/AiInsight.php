<?php

namespace App\Models;

use App\Enums\ProactiveInsightSeverity;
use App\Enums\ProactiveInsightSource;
use App\Enums\ProactiveInsightStatus;
use App\Enums\ProactiveInsightType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A persisted proactive insight (Phase 11.9): a deterministic, rule-based,
 * advisory alert raised over authoritative FinancePro records. Insights are
 * read-only analytical state — they never form a financial record and never
 * influence a FinancePro business rule or an automated decision. Resolution
 * and reuse are always driven by domain state or an explicit human decision,
 * never by the AI model.
 */
class AiInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'branch_id',
        'type', 'severity', 'title', 'summary', 'recommendation',
        'source_type', 'source_id', 'object_type', 'object_id',
        'dedup_key', 'status', 'period_start', 'period_end', 'metadata',
        'generated_at', 'data_through',
        'acknowledged_by', 'acknowledged_at',
        'resolved_by', 'resolved_at',
        'dismissed_by', 'dismissed_at', 'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'generated_at' => 'datetime',
            'data_through' => 'date',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'expired_at' => 'datetime',
            'type' => ProactiveInsightType::class,
            'severity' => ProactiveInsightSeverity::class,
            'source_type' => ProactiveInsightSource::class,
            'status' => ProactiveInsightStatus::class,
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

    public function object(): MorphTo
    {
        return $this->morphTo();
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForOrganizations(Builder $query, array $organizationIds): Builder
    {
        return $query->whereIn('organization_id', $organizationIds);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProactiveInsightStatus::New->value,
            ProactiveInsightStatus::Read->value,
            ProactiveInsightStatus::Acknowledged->value,
        ]);
    }
}
