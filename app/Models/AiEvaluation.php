<?php

namespace App\Models;

use App\Enums\AiEvaluationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A human review of one piece of feedback.
 *
 * An evaluation is the only route into the learning dataset, and approval is
 * the only automation this phase permits. Scores are a bounded pass/fail set
 * over fixed criteria; there is no numeric ML score, and an evaluation never
 * changes FinancePro business data.
 */
class AiEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_feedback_id',
        'ai_message_id',
        'ai_conversation_id',
        'evaluator_id',
        'organization_id',
        'branch_id',
        'status',
        'scores',
        'notes',
        'rejection_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiEvaluationStatus::class,
            'scores' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(AiFeedback::class, 'ai_feedback_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function learningExample(): HasOne
    {
        return $this->hasOne(AiLearningExample::class, 'ai_evaluation_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AiEvaluationStatus::Pending->value,
            AiEvaluationStatus::InReview->value,
        ]);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
