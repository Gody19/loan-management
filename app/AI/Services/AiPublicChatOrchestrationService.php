<?php

namespace App\AI\Services;

use App\AI\DTOs\AiMessageData;
use App\AI\DTOs\AiRequestData;
use App\AI\DTOs\AiResponseData;
use App\AI\Exceptions\AiUnavailableException;
use App\Enums\AiConversationStatus;
use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Services\AuditService;
use Throwable;

/**
 * Server-side orchestration for the public landing-page assistant.
 *
 * This is the only flow that answers an unauthenticated visitor with a stored,
 * session-anchored conversation. It is intentionally narrow:
 *
 *  1. The conversation is already resolved from the visitor session (a
 *     server-generated uuid, never browser-supplied) by the controller.
 *  2. The visitor message is persisted on that conversation.
 *  3. Only public-visible knowledge documents (visibility=public) may be
 *     consulted, through AiKnowledgeRetrievalService::searchPublic, which
 *     never throws — a retrieval failure simply yields a plain answer.
 *  4. The provider request is bounded (max_history_messages window) and
 *     carries the platform system instructions plus the public-specific
 *     instructions; nothing else authored by the visitor can influence scope.
 *
 * There is deliberately no membership, tenant, tool, or model-output path into
 * anything other than the public conversation and public knowledge. Provider
 * failures propagate as AiUnavailableException so the controller can answer
 * with the controlled 503 payload.
 */
class AiPublicChatOrchestrationService
{
    public function __construct(
        private readonly AiProviderService $providerService,
        private readonly AiConversationService $conversations,
        private readonly AiKnowledgeRetrievalService $knowledge,
        private readonly AuditService $audit,
    ) {}

    /**
     * @throws AiUnavailableException
     */
    public function answer(AiConversation $conversation, string $message): AiResponseData
    {
        $this->conversations->appendMessage($conversation, AiMessageRole::User, $message);

        $systemContext = $this->publicKnowledgeContext($message);

        $request = new AiRequestData(
            messages: $this->boundedMessages($conversation, $systemContext),
            model: $this->providerService->defaultModel(),
            temperature: config('ai.temperature') !== null ? (float) config('ai.temperature') : null,
            maxOutputTokens: (int) config('ai.public_chat.max_output_tokens', 512),
            metadata: [
                'scope' => 'public',
                'conversation_id' => $conversation->id,
                'persisted' => true,
            ],
            conversationId: (int) $conversation->id,
        );

        $response = $this->providerService->generate($request);

        $this->conversations->appendMessage($conversation, AiMessageRole::Assistant, $response->content, [
            'provider_message_id' => $response->providerRequestId,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'total_tokens' => $response->totalTokens,
            'model' => $response->model,
            'metadata' => $response->metadata,
        ]);

        $conversation->update([
            'provider' => $response->provider,
            'model' => $response->model,
            'status' => AiConversationStatus::Active,
        ]);

        $this->audit->log('ai.provider.requested', $conversation, [], [
            'provider' => $response->provider,
            'model' => $response->model,
            'scope' => 'public',
        ]);

        return $response;
    }

    /**
     * Bounded provider messages for a public conversation: platform system
     * instructions, public-specific instructions, then the visitor's own
     * stored history (oldest first) capped at the configured public window.
     * The visitor's question is part of that history — nothing is duplicated.
     *
     * @param  AiMessageData[]  $systemContext
     * @return AiMessageData[]
     */
    protected function boundedMessages(AiConversation $conversation, array $systemContext): array
    {
        $limit = max(1, (int) config('ai.public_chat.max_history_messages', 8));

        $history = $this->conversations->history($conversation, $limit);

        $instructions = trim((string) config('ai.system_instructions', ''));
        $publicInstructions = trim((string) config('ai.public_system_instructions', ''));

        $context = array_values(array_filter(
            $systemContext,
            fn ($message) => $message instanceof AiMessageData,
        ));

        if ($instructions !== '') {
            $context = array_merge(
                [new AiMessageData(AiMessageRole::System, $instructions)],
                $context,
            );
        }

        if ($publicInstructions !== '') {
            $context = array_merge(
                [new AiMessageData(AiMessageRole::System, $publicInstructions)],
                $context,
            );
        }

        return array_merge($context, $history);
    }

    /**
     * Optional, public-only knowledge context for the question. Never throws:
     * unauthorized, unavailable, or irrelevant retrieval just means the public
     * assistant answers conversationally from its system instructions.
     *
     * @return AiMessageData[]
     */
    protected function publicKnowledgeContext(string $message): array
    {
        try {
            $results = $this->knowledge->searchPublic($message);

            if ($results === []) {
                return [];
            }

            return [AiKnowledgeResultFormatter::format($results)];
        } catch (Throwable) {
            return [];
        }
    }
}
