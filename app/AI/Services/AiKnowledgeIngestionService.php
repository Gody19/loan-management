<?php

namespace App\AI\Services;

use App\AI\Exceptions\AiUnavailableException;
use App\Enums\AiKnowledgeDocumentStatus;
use App\Enums\AiKnowledgeDocumentType;
use App\Enums\AiKnowledgeScope;
use App\Jobs\ProcessAiKnowledgeDocument;
use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lifecycle and ingestion for the approved knowledge base.
 *
 * Documents are stored with their full approved content; chunks and embedding
 * vectors are derived from that content at ingestion time. Ingestion is
 * idempotent (checksum-verified), retry-safe, tenant-aware, and audited. A
 * changed document is versioned and reprocessed; a failed ingest leaves the
 * document in the failed state for a controlled retry.
 *
 * This service performs no authorization — the creation scope comes from the
 * controller/guardrail, and retrieval authorization is the job of
 * AiKnowledgePolicy + AiKnowledgeRetrievalService.
 */
class AiKnowledgeIngestionService
{
    public function __construct(
        private readonly AiKnowledgeChunkingService $chunking,
        private readonly AiEmbeddingProviderService $embeddings,
        private readonly AuditService $audit,
    ) {}

    /**
     * Create a new approved-text knowledge document and dispatch processing.
     *
     * @throws AiUnavailableException when the embedding provider cannot resolve
     */
    public function store(
        User $user,
        string $title,
        AiKnowledgeDocumentType $type,
        AiKnowledgeScope $scope,
        string $content,
        ?int $organizationId = null,
        ?int $branchId = null,
        ?int $vicobaGroupId = null,
        ?string $description = null,
        ?string $source = null,
    ): AiKnowledgeDocument {
        $content = trim($content);

        if ($content === '') {
            throw new AiUnavailableException();
        }

        $document = AiKnowledgeDocument::create([
            'organization_id' => $scope === AiKnowledgeScope::Global ? null : $organizationId,
            'branch_id' => in_array($scope, [AiKnowledgeScope::Branch, AiKnowledgeScope::Group], true) ? $branchId : null,
            'vicoba_group_id' => $scope === AiKnowledgeScope::Group ? $vicobaGroupId : null,
            'title' => trim($title),
            'document_type' => $type,
            'description' => $description !== null ? trim($description) : null,
            'source' => $source !== null ? trim($source) : 'internal',
            'content' => $content,
            'version' => 1,
            'status' => AiKnowledgeDocumentStatus::Draft,
            'visibility' => $scope,
            'checksum' => null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->audit->log('ai.knowledge.created', $document, [], [
            'document_id' => $document->id,
            'document_type' => $type->value,
            'visibility' => $scope->value,
        ]);

        ProcessAiKnowledgeDocument::dispatch($document->id);

        return $document;
    }

    /**
     * Process (chunk + embed) and activate a document. Idempotent: an already
     * processed document with an unchanged checksum is a no-op. A changed
     * document is versioned and its chunks replaced.
     */
    public function ingest(AiKnowledgeDocument $document): AiKnowledgeDocument
    {
        $document->update([
            'status' => AiKnowledgeDocumentStatus::Processing,
            'updated_by' => auth()->id(),
        ]);

        $content = (string) $document->content;
        $checksum = $this->chunking->checksum($content);

        if ($document->status->value === AiKnowledgeDocumentStatus::Processing->value
            && $document->checksum !== null
            && $document->checksum === $checksum
            && $document->chunks()->exists()) {
            $document->update(['status' => AiKnowledgeDocumentStatus::Active]);

            $this->audit->log('ai.knowledge.processed', $document, [], [
                'document_id' => $document->id,
                'checksum' => $checksum,
                'chunks' => $document->chunks()->count(),
                'idempotent' => true,
            ]);

            return $document;
        }

        try {
            $this->embeddings->resolve();

            $chunks = $this->chunking->chunk(
                $content,
                (int) config('ai.knowledge.chunk_size', 1200),
                (int) config('ai.knowledge.chunk_overlap', 150),
            );

            if ($chunks === []) {
                throw new AiUnavailableException();
            }

            $vectors = $this->embeddings->resolve()->embedBatch($chunks);

            if (count($vectors) !== count($chunks)) {
                throw new AiUnavailableException();
            }

            $wasProcessed = $document->checksum !== null;
            $versionChanged = $wasProcessed && $document->checksum !== $checksum;

            $newVersion = $versionChanged ? (int) $document->version + 1 : (int) $document->version;

            DB::transaction(function () use ($document, $chunks, $vectors, $checksum, $newVersion) {
                $document->chunks()->delete();

                foreach ($chunks as $index => $chunkContent) {
                    AiKnowledgeChunk::create([
                        'ai_knowledge_document_id' => $document->id,
                        'chunk_index' => $index,
                        'content' => $chunkContent,
                        'content_hash' => hash('sha256', $chunkContent),
                        'token_count' => $this->chunking->estimateTokens($chunkContent),
                        'estimated_size' => $this->chunking->estimatedSize($chunkContent),
                        'embedding' => array_values($vectors[$index]),
                    ]);
                }

                $document->update([
                    'version' => $newVersion,
                    'status' => AiKnowledgeDocumentStatus::Active,
                    'checksum' => $checksum,
                    'updated_by' => auth()->id(),
                ]);
            });

            $this->audit->log(
                $versionChanged ? 'ai.knowledge.updated' : 'ai.knowledge.processed',
                $document,
                $versionChanged ? ['version' => (int) $document->version - 1] : [],
                [
                    'document_id' => $document->id,
                    'version' => $newVersion,
                    'checksum' => $checksum,
                    'chunks' => count($chunks),
                    'idempotent' => false,
                ],
            );

            return $document->fresh() ?? $document;
        } catch (Throwable $exception) {
            Log::warning('AI knowledge ingestion failed.', [
                'document_id' => $document->id,
                'exception' => $exception::class,
            ]);

            $this->fail($document);

            throw new AiUnavailableException();
        }
    }

    /**
     * Mark a document as archived (removed from retrieval).
     */
    public function archive(AiKnowledgeDocument $document): AiKnowledgeDocument
    {
        $document->update([
            'status' => AiKnowledgeDocumentStatus::Archived,
            'updated_by' => auth()->id(),
        ]);

        $this->audit->log('ai.knowledge.archived', $document, [], [
            'document_id' => $document->id,
        ]);

        return $document;
    }

    /**
     * Mark a document as failed. Kept public so callers can record a failed
     * ingest even when processing already threw.
     */
    public function fail(AiKnowledgeDocument $document): void
    {
        $document->update([
            'status' => AiKnowledgeDocumentStatus::Failed,
            'updated_by' => auth()->id(),
        ]);

        $this->audit->log('ai.knowledge.failed', $document, [], [
            'document_id' => $document->id,
        ]);
    }
}