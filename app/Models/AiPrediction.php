<?php

namespace App\Models;

use App\Enums\PredictionConfidence;
use App\Enums\PredictiveDataQuality;
use App\Enums\PredictiveInsightStatus;
use App\Enums\PredictiveInsightType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persisted Predictive Intelligence insight (Phase 11.8): the snapshot of a
 * deterministic statistical baseline for one organization at one data
 * cutoff. This is analytical/advisory state only — it never forms a financial
 * record, never influences loan eligibility/approval/rates, and never carries
 * member-level detail.
 */
class AiPrediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'type', 'status', 'scope', 'method', 'model_version',
        'target_period', 'data_through', 'horizon', 'confidence', 'data_quality',
        'value_total', 'currency', 'series', 'factors', 'assumptions', 'explanation',
        'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'data_through' => 'date',
            'horizon' => 'integer',
            'value_total' => 'decimal:2',
            'series' => 'array',
            'factors' => 'array',
            'assumptions' => 'array',
            'generated_at' => 'datetime',
            'type' => PredictiveInsightType::class,
            'status' => PredictiveInsightStatus::class,
            'confidence' => PredictionConfidence::class,
            'data_quality' => PredictiveDataQuality::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForOrganizations(Builder $query, array $organizationIds): Builder
    {
        return $query->whereIn('organization_id', $organizationIds);
    }

    public function scopeOfType(Builder $query, PredictiveInsightType $type): Builder
    {
        return $query->where('type', $type->value);
    }
}
