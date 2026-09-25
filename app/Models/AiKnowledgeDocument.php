<?php

namespace App\Models;

use App\Enums\AiKnowledgeDocumentStatus;
use App\Enums\AiKnowledgeDocumentType;
use App\Enums\AiKnowledgeScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An approved FinancePro knowledge document.
 *
 * Only documents with status = active are ever eligible for retrieval, and
 * retrieval eligibility is scoped by the visibility hierarchy (global,
 * organization, branch, group) against the trusted AI tenant context. A
 * document is never an authorization source — its content cannot grant
 * privileges, approve records, or change finance state.
 */
class AiKnowledgeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'vicoba_group_id',
        'title',
        'document_type',
        'description',
        'source',
        'content',
        'version',
        'status',
        'visibility',
        'checksum',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => AiKnowledgeDocumentType::class,
            'status' => AiKnowledgeDocumentStatus::class,
            'visibility' => AiKnowledgeScope::class,
            'metadata' => 'array',
            'version' => 'integer',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AiKnowledgeChunk::class);
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AiKnowledgeDocumentStatus::Active->value);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}