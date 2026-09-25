<?php

namespace App\Models;

use App\Enums\AiFeedbackStatus;
use App\Enums\AiFeedbackType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A user's reaction to one AI assistant response.
 *
 * Feedback is untrusted user-supplied data. The correction text is never
 * executed, never interpreted as a business rule, and never written back to
 * any FinancePro record. It can only ever become a learning-dataset example
 * after an authorized human reviewer approves an evaluation.
 */
class AiFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_message_id',
        'ai_conversation_id',
        'user_id',
        'organization_id',
        'branch_id',
        'ai_model_version_id',
        'type',
        'status',
        'correction',
        'reason',
        'provider',
        'model',
    ];

    protected function casts(): array
    {
        return [
            'type' => AiFeedbackType::class,
            'status' => AiFeedbackStatus::class,
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(AiModelVersion::class, 'ai_model_version_id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(AiEvaluation::class);
    }

    public function learningExample(): HasOne
    {
        return $this->hasOne(AiLearningExample::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AiFeedbackStatus::Submitted->value,
            AiFeedbackStatus::InReview->value,
        ]);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
