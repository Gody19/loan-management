<?php

namespace Database\Factories;

use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiKnowledgeChunkFactory extends Factory
{
    protected $model = AiKnowledgeChunk::class;

    public function definition(): array
    {
        $content = fake()->text(80);

        return [
            'ai_knowledge_document_id' => AiKnowledgeDocument::factory(),
            'chunk_index' => 0,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'token_count' => max(1, str_word_count($content)),
            'estimated_size' => strlen($content),
            'embedding' => null,
            'metadata' => null,
        ];
    }
}