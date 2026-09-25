<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\AiKnowledgeResultData;
use App\AI\Services\AiKnowledgeRetrievalService;
use App\Models\User;

/**
 * ai.knowledge.search — retrieve approved FinancePro knowledge within the
 * acting user's authorized tenant scope.
 *
 * The model may only supply the semantic terms to search for (search_term).
 * Scope, tenant identifiers, and permission are always enforced by the trusted
 * context and by AiKnowledgeRetrievalService; results are returned as a
 * narrowed, read-only subset (never raw storage paths, metadata, or vectors).
 */
class KnowledgeSearchTool implements AiToolInterface
{
    public function __construct(
        private readonly AiKnowledgeRetrievalService $retrieval,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $results = $this->retrieval->search(
            $context,
            (string) ($arguments['search_term'] ?? ''),
            isset($arguments['top_k']) ? (int) $arguments['top_k'] : null,
        );

        return [
            'results' => array_map(
                fn (AiKnowledgeResultData $result) => $result->toArray(),
                $results,
            ),
        ];
    }
}