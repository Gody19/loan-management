<?php

namespace App\Models;

use App\Enums\AiAnomalyFindingStatus;
use App\Enums\FinancialAnomalyType;
use App\Enums\FinancialFindingSeverity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persisted Financial Intelligence anomaly finding: the review trail for a
 * deterministic detection. This is analytical state only — it never forms a
 * financial record and never influences FinancePro business rules.
 */
class AiAnomalyFinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'branch_id', 'member_id', 'loan_id',
        'type', 'severity', 'title', 'description', 'amount', 'currency',
        'source_type', 'source_id', 'metadata', 'detection_date', 'detected_at',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'detection_date' => 'date',
            'detected_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'type' => FinancialAnomalyType::class,
            'severity' => FinancialFindingSeverity::class,
            'status' => AiAnomalyFindingStatus::class,
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

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForOrganizations(Builder $query, array $organizationIds): Builder
    {
        return $query->whereIn('organization_id', $organizationIds);
    }
}
