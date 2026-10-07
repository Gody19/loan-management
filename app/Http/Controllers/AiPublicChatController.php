<?php

namespace App\Http\Controllers;

use App\AI\Exceptions\AiUnavailableException;
use App\AI\Policies\AiToolPolicy;
use App\AI\Services\AiConversationService;
use App\AI\Services\AiPublicChatOrchestrationService;
use App\Models\AiConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public landing-page assistant (Phase 11.7.1).
 *
 * Two anonymous, session-anchored endpoints:
 *
 *   POST /ai/public/chat                    ai.public.chat
 *   GET  /ai/public/conversations/current   ai.public.conversations.current
 *
 * The visitor's conversation identity is a server-generated uuid kept only in
 * their session; the browser never supplies a conversation id, so there is no
 * enumeration or cross-visitor access surface. Only public knowledge
 * (visibility=public RAG documents) is ever consulted, everything is throttled
 * per (IP + session), and no tenant/member argument is ever accepted. The
 * stateless POST /ai/chat/guest (AiController::storeGuest) remains untouched
 * for backwards compatibility.
 */
class AiPublicChatController extends Controller
{
    public function __construct(
        private readonly AiConversationService $conversations,
        private readonly AiPublicChatOrchestrationService $orchestrator,
    ) {}

    /**
     * The public surface is available only when both the AI feature and the
     * public assistant switch are on and a provider can be resolved. A single
     * controlled 503 payload is returned in every other case.
     */
    protected function enabled(): bool
    {
        return (bool) config('ai.public_chat.enabled', true)
            && $this->conversations->isEnabled()
            && $this->conversations->isAvailable();
    }

    /**
     * Bound how long an ANONYMOUS request may hold a PHP worker.
     *
     * This previously called set_time_limit(600), which let an unauthenticated
     * visitor pin a worker for up to ten minutes per request. Combined with the
     * per-IP chat throttle that is a cheap way to exhaust the FPM pool on a
     * financial application. The wall clock is now a configurable bound that
     * sits above the provider timeout and far below ten minutes, and the
     * provider's own timeout remains the real ceiling.
     */
    protected function extendTimeLimit(): void
    {
        set_time_limit((int) config('ai.public_chat.max_execution_seconds', 120));
    }

    protected function unavailable(): JsonResponse
    {
        return response()->json(['message' => AiUnavailableException::SAFE_MESSAGE], 503);
    }

    /**
     * Restore the visitor's current public conversation for a page refresh.
     * Returns a null conversation when the session has none (first visit).
     */
    public function current(Request $request): JsonResponse
    {
        if (! $this->enabled()) {
            return $this->unavailable();
        }

        $conversation = $this->sessionConversation($request);

        if (! $conversation) {
            return response()->json([
                'data' => [
                    'conversation_id' => null,
                    'messages' => [],
                ],
            ]);
        }

        $messages = $this->conversations->messages($conversation)->map(
            fn (mixed $message) => [
                'id' => $message->id,
                'role' => $message->role->value,
                'content' => $message->content,
                'created_at' => $message->created_at?->toISOString(),
            ]
        );

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Answer a public visitor question. The visitor message is the only field
     * accepted; every privilege-influencing key is rejected outright.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->enabled()) {
            return $this->unavailable();
        }

        $this->extendTimeLimit();

        $rules = [
            'message' => [
                'required',
                'string',
                'max:'.(int) config('ai.public_chat.max_message_length', 4000),
            ],
        ];

        foreach (AiToolPolicy::FORBIDDEN_ARGUMENT_KEYS as $key) {
            $rules[$key] = 'prohibited';
        }

        $validated = $request->validate($rules);

        $conversation = $this->resolveSessionConversation($request);

        try {
            $response = $this->orchestrator->answer($conversation, (string) $validated['message']);
        } catch (AiUnavailableException) {
            return $this->unavailable();
        }

        $assistantMessageId = $this->conversations->lastAssistantMessage($conversation)?->id;

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'message_id' => $assistantMessageId,
                'provider' => $response->provider,
                'model' => $response->model,
                'content' => $response->content,
                'usage' => [
                    'input_tokens' => $response->inputTokens,
                    'output_tokens' => $response->outputTokens,
                    'total_tokens' => $response->totalTokens,
                ],
            ],
        ], 200);
    }

    /**
     * The session-anchored conversation, if one exists for this visitor.
     */
    protected function sessionConversation(Request $request): ?AiConversation
    {
        $uuid = $request->session()->get('ai_public_conversation_uuid');

        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return $this->conversations->findPublicByUuid($uuid);
    }

    /**
     * Resolve (or create) the visitor's session-anchored public conversation.
     * The uuid is always generated and stored server-side; a browser-supplied
     * id is never trusted.
     */
    protected function resolveSessionConversation(Request $request): AiConversation
    {
        $uuid = $request->session()->get('ai_public_conversation_uuid');

        if (! is_string($uuid) || $uuid === '') {
            $uuid = (string) Str::uuid();
            $request->session()->put('ai_public_conversation_uuid', $uuid);
        }

        $conversation = $this->conversations->findPublicByUuid($uuid);

        if ($conversation) {
            return $conversation;
        }

        return $this->conversations->createPublic($uuid);
    }
}
