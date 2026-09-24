<?php

namespace App\Models;

use App\Enums\AiConversationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'branch_id',
        'vicoba_group_id',
        'title',
        'status',
        'provider',
        'model',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiConversationStatus::class,
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
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

    public function vicobaGroup(): BelongsTo
    {
        return $this->belongsTo(VicobaGroup::class, 'vicoba_group_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AiConversationStatus::Active->value);
    }
}