<?php

namespace App\AI\DTOs;

/**
 * A single knowledge-base retrieval result.
 *
 * Exposes a deliberately narrowed surface to callers and the provider-facing
 * formatter: identifiers, title, type, scope, version, matching chunk content,
 * similarity, and a human source reference. Raw document metadata, storage
 * paths, embedding vectors, and tenant-internal identifiers are never exposed.
 */
final class AiKnowledgeResultData
{
    public function __construct(
        public readonly int $documentId,
        public readonly string $title,
        public readonly string $documentType,
        public readonly string $scope,
        public readonly int $chunkId,
        public readonly string $content,
        public readonly int $version,
        public readonly float $similarity,
        public readonly string $sourceReference,
    ) {}

    /**
     * @return array{document_id: int, title: string, document_type: string, scope: string, chunk_id: int, content: string, version: int, similarity: float, source: string}
     */
    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'title' => $this->title,
            'document_type' => $this->documentType,
            'scope' => $this->scope,
            'chunk_id' => $this->chunkId,
            'content' => $this->content,
            'version' => $this->version,
            'similarity' => round($this->similarity, 6),
            'source' => $this->sourceReference,
        ];
    }
}