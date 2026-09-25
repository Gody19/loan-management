<?php

namespace App\Models;

use App\Enums\AiLearningExampleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An approved, sanitized learning example.
 *
 * This row is the export boundary. It only ever exists because an authorized
 * human approved an evaluation, and it holds sanitized snapshots rather than
 * live message content so it survives conversation-history pruning without
 * retaining the original private text. Approved examples are immutable: a
 * correction supersedes the old row instead of overwriting it.
 */
class AiLearningExample extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_evaluation_id',
        'ai_feedback_id',
        'organization_id',
        'branch_id',
        'dataset_version',
        'status',
        'input_text',
        'original_response',
        'corrected_response',
        'evaluation_metadata',
        'sanitization_report',
        'approved_by',
        'approved_at',
        'superseded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiLearningExampleStatus::class,
            'evaluation_metadata' => 'array',
            'sanitization_report' => 'array',
            'dataset_version' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(AiEvaluation::class, 'ai_evaluation_id');
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(AiFeedback::class, 'ai_feedback_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AiLearningExampleStatus::Active->value);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
