<?php

namespace App\AI\Services;

use App\AI\DTOs\AiContextData;
use App\AI\DTOs\AiKnowledgeResultData;
use App\AI\Exceptions\AiToolException;
use App\AI\Policies\AiKnowledgePolicy;
use App\Enums\AiKnowledgeDocumentStatus;
use App\Enums\AiKnowledgeScope;
use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tenant-aware, permission-aware retrieval over the approved knowledge base.
 *
 * The scope filter (which documents the acting user may see) is applied to the
 * candidate document set BEFORE any similarity computation — unauthorized
 * documents can never enter the candidate pool. Similarity is computed over
 * the already-scoped chunks in application code, so no raw SQL or dynamic
 * query execution is ever involved.
 *
 * Retrieval is strictly READ-ONLY against documents already approved as
 * authoritative knowledge. It never reflects a document's instructions back as
 * authority: content is delivered as reference data through
 * AiKnowledgeResultFormatter.
 */
class AiKnowledgeRetrievalService
{
    public function __construct(
        private readonly AiEmbeddingProviderService $embeddings,
        private readonly AiKnowledgePolicy $policy,
        private readonly AuditService $audit,
    ) {}

    /**
     * Search the approved knowledge base visible to the acting context.
     *
     * @return AiKnowledgeResultData[]
     *
     * @throws AiToolException when unauthorized or the embedding provider fails
     */
    public function search(AiContextData $context, string $query, ?int $topK = null): array
    {
        $query = trim($query);

        if ($query === '') {
            throw new AiToolException('validation_failed');
        }

        if (! $context->hasPermission('ai.knowledge.search')) {
            throw new AiToolException('unauthorized');
        }

        if (! $this->embeddings->isAvailable()) {
            throw new AiToolException('service_unavailable');
        }

        $topK = $this->boundedTopK($topK);

        if ($topK < 1) {
            return [];
        }

        $documentIds = $this->authorizedDocumentIds($context);

        if ($documentIds->isEmpty()) {
            $this->auditRetrieval($context, [], 0);

            return [];
        }

        $chunks = AiKnowledgeChunk::query()
            ->whereIn('ai_knowledge_document_id', $documentIds)
            ->with('document')
            ->get();

        if ($chunks->isEmpty()) {
            $this->auditRetrieval($context, [], 0);

            return [];
        }

        $results = $this->rankChunks($chunks, $query, $topK);

        $this->auditRetrieval($context, $results, count($results));

        return $results;
    }

    /**
     * Public knowledge retrieval for the landing-page assistant.
     *
     * Unlike search(), there is no signed-in user and therefore no permission
     * check and no tenant context: only documents explicitly published with
     * visibility=public (and all tenant columns null) are eligible, which are
     * exactly the documents the public surface may answer general questions
     * from. It shares the certified scoring pipeline and never throws — any
     * retrieval failure simply yields no knowledge, so the public chat falls
     * back to a plain conversational answer.
     *
     * @return AiKnowledgeResultData[]
     */
    public function searchPublic(string $query, ?int $topK = null): array
    {
        $query = trim($query);

        if ($query === '' || ! $this->embeddings->isAvailable()) {
            return [];
        }

        $topK = $this->boundedTopK($topK);

        if ($topK < 1) {
            return [];
        }

        $documentIds = AiKnowledgeDocument::query()
            ->where('status', AiKnowledgeDocumentStatus::Active->value)
            ->where('visibility', AiKnowledgeScope::Public->value)
            ->whereNull('organization_id')
            ->whereNull('branch_id')
            ->whereNull('vicoba_group_id')
            ->select('id')
            ->pluck('id');

        if ($documentIds->isEmpty()) {
            $this->auditPublicRetrieval([], 0);

            return [];
        }

        $chunks = AiKnowledgeChunk::query()
            ->whereIn('ai_knowledge_document_id', $documentIds)
            ->with('document')
            ->get();

        if ($chunks->isEmpty()) {
            $this->auditPublicRetrieval([], 0);

            return [];
        }

        $results = $this->rankChunks($chunks, $query, $topK);

        $this->auditPublicRetrieval($results, count($results));

        return $results;
    }

    /**
     * Shared relevance pipeline: embed the query once, score every eligible
     * chunk, apply the similarity floor, keep the top-K, then bound the total
     * context tokens handed to a provider.
     *
     * @param  Collection<int, AiKnowledgeChunk>  $chunks
     * @return AiKnowledgeResultData[]
     */
    protected function rankChunks(Collection $chunks, string $query, int $topK): array
    {
        $queryVector = $this->embeddingVector($query);

        $results = $chunks
            ->map(fn (AiKnowledgeChunk $chunk) => $this->score($chunk, $queryVector))
            ->filter(fn (AiKnowledgeResultData $result) => $result->similarity > $this->minimumSimilarity())
            ->sortByDesc(fn (AiKnowledgeResultData $result) => $result->similarity)
            ->take($topK)
            ->values()
            ->all();

        return $this->limitByContextTokens($results);
    }

    /**
     * Authorized active documents for the context: scope-filtered document ids.
     * The filter uses parameterized where-clauses only — never raw SQL with
     * user input — and Global/Organization/Branch/Group scopes are combined
     * with OR semantics per the hierarchy.
     *
     * @return Collection<int>
     */
    protected function authorizedDocumentIds(AiContextData $context): Collection
    {
        $query = AiKnowledgeDocument::query()
            ->where('status', AiKnowledgeDocumentStatus::Active->value)
            ->select('id');

        if ($context->isSuperAdmin) {
            return $query->pluck('id');
        }

        $query->where(function (Builder $builder) use ($context) {
            $builder->where('visibility', AiKnowledgeScope::Global->value)
                ->orWhere('visibility', AiKnowledgeScope::Public->value);

            if ($context->organizationIds !== []) {
                $builder->orWhere(function (Builder $sub) use ($context) {
                    $sub->where('visibility', AiKnowledgeScope::Organization->value)
                        ->whereIn('organization_id', $context->organizationIds);
                });
            }

            if ($context->branchIds !== []) {
                $builder->orWhere(function (Builder $sub) use ($context) {
                    $sub->where('visibility', AiKnowledgeScope::Branch->value)
                        ->whereIn('branch_id', $context->branchIds);
                });
            }

            if ($context->vicobaGroupIds !== []) {
                $builder->orWhere(function (Builder $sub) use ($context) {
                    $sub->where('visibility', AiKnowledgeScope::Group->value)
                        ->whereIn('vicoba_group_id', $context->vicobaGroupIds);
                });
            }
        });

        return $query->pluck('id');
    }

    /**
     * Certified DTO for a chunk, computing cosine similarity against the query
     * vector. Also re-verifies document eligibility at the row level (retrieval
     * runs over chunks of already-authorized documents, but the same policy is
     * applied again here as defense in depth).
     */
    protected function score(AiKnowledgeChunk $chunk, array $queryVector): AiKnowledgeResultData
    {
        $document = $chunk->document;

        if (! $document instanceof AiKnowledgeDocument
            || $document->status->value !== AiKnowledgeDocumentStatus::Active->value) {
            return $this->emptyResult($chunk);
        }

        $similarity = $this->cosineSimilarity($queryVector, $this->chunkVector($chunk));

        $sourceReference = sprintf(
            '%s · %s · v%d',
            $document->source !== null && $document->source !== '' ? $document->source : 'internal',
            $document->title,
            (int) $document->version,
        );

        return new AiKnowledgeResultData(
            documentId: (int) $document->id,
            title: (string) $document->title,
            documentType: $document->document_type->value,
            scope: $document->visibility->value,
            chunkId: (int) $chunk->id,
            content: (string) $chunk->content,
            version: (int) $document->version,
            similarity: $similarity,
            sourceReference: $sourceReference,
        );
    }

    /**
     * A zero-similarity placeholder so an ineligible chunk is naturally ranked
     * last and dropped by the similarity filter instead of leaking.
     */
    protected function emptyResult(AiKnowledgeChunk $chunk): AiKnowledgeResultData
    {
        return new AiKnowledgeResultData(
            documentId: (int) ($chunk->document?->id ?? 0),
            title: $chunk->document?->title ?? '',
            documentType: 'unknown',
            scope: '',
            chunkId: (int) $chunk->id,
            content: (string) $chunk->content,
            version: 1,
            similarity: 0.0,
            sourceReference: '',
        );
    }

    protected function embeddingVector(string $query): array
    {
        return $this->embeddings->resolve()->embed($query);
    }

    /**
     * @return list<float>
     */
    protected function chunkVector(AiKnowledgeChunk $chunk): array
    {
        $embedding = $chunk->embedding;

        if (! is_array($embedding)) {
            return [];
        }

        return array_values(array_map('floatval', $embedding));
    }

    /**
     * @param  list<float>  $vectorA
     * @param  list<float>  $vectorB
     */
    protected function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        if ($vectorA === [] || $vectorB === [] || count($vectorA) !== count($vectorB)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($vectorA as $index => $valueA) {
            $valueB = $vectorB[$index];
            $dot += $valueA * $valueB;
            $normA += $valueA * $valueA;
            $normB += $valueB * $valueB;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    protected function boundedTopK(?int $requested): int
    {
        $configured = max(1, (int) config('ai.knowledge.top_k', 5));

        if ($requested === null || $requested < 1) {
            return $configured;
        }

        return min($configured, $requested);
    }

    /**
     * Server-side relevance floor. Results at or below this similarity never
     * reach the model, which keeps irrelevant documents out of the prompt and
     * lets the "no relevant approved knowledge found" behavior trigger
     * coherently.
     */
    protected function minimumSimilarity(): float
    {
        return max(0.0, (float) config('ai.knowledge.min_similarity', 0.0));
    }

    /**
     * Bounds the total knowledge context handed to a provider in one request.
     * Chunks are accepted most-relevant-first until the configured token budget
     * is exhausted, so the model can never be flooded with unscoped context.
     *
     * @param  AiKnowledgeResultData[]  $results
     * @return AiKnowledgeResultData[]
     */
    protected function limitByContextTokens(array $results): array
    {
        $budget = max(1, (int) config('ai.knowledge.max_context_tokens', 2000));

        $kept = [];
        $used = 0;

        foreach ($results as $result) {
            $tokens = max(1, str_word_count($result->content));

            if ($used + $tokens > $budget) {
                break;
            }

            $kept[] = $result;
            $used += $tokens;
        }

        return $kept;
    }

    /**
     * Audit a retrieval with safe metadata only: never the prompt itself,
     * embedding vectors, keys, or raw sensitive content.
     *
     * @param  AiKnowledgeResultData[]  $results
     */
    protected function auditRetrieval(AiContextData $context, array $results, int $count): void
    {
        $this->audit->log('ai.knowledge.retrieved', null, [], [
            'user_id' => $context->userId,
            'document_ids' => array_values(array_unique(array_map(
                fn (AiKnowledgeResultData $result) => $result->documentId,
                $results,
            ))),
            'result_count' => $count,
            'top_k' => (int) config('ai.knowledge.top_k', 5),
        ]);
    }

    /**
     * Public-surface retrieval audit: never references a user, only which
     * public documents were returned and how many.
     *
     * @param  AiKnowledgeResultData[]  $results
     */
    protected function auditPublicRetrieval(array $results, int $count): void
    {
        $this->audit->log('ai.knowledge.retrieved', null, [], [
            'scope' => AiKnowledgeScope::Public->value,
            'document_ids' => array_values(array_unique(array_map(
                fn (AiKnowledgeResultData $result) => $result->documentId,
                $results,
            ))),
            'result_count' => $count,
            'top_k' => (int) config('ai.knowledge.top_k', 5),
        ]);
    }
}
