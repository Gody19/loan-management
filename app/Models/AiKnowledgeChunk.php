<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deterministic chunk of an approved knowledge document together with the
 * embedding vector used for similarity retrieval.
 *
 * The embedding is stored as a JSON value inside the application database so
 * the same schema works on MySQL 8+ and the SQLite ':memory:' test database —
 * there is no second storage system and no external vector database. Retrieval
 * always filters by authorized scope first and only then computes similarity
 * over the already-scoped candidate set.
 */
class AiKnowledgeChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_knowledge_document_id',
        'chunk_index',
        'content',
        'content_hash',
        'token_count',
        'estimated_size',
        'embedding',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'embedding' => 'array',
            'metadata' => 'array',
            'token_count' => 'integer',
            'estimated_size' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeDocument::class, 'ai_knowledge_document_id');
    }
}