<?php

namespace App\Http\Controllers;

use App\AI\Exceptions\AiUnavailableException;
use App\AI\Services\AiConversationService;
use App\Models\AiConversation;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minimal internal JSON endpoints for the AI foundation. There is no public
 * API surface here: every route requires an authenticated user with the ai.*
 * permission. Responses never echo provider internals and always return a
 * controlled 503 payload when the AI capability is unavailable.
 */
class AiController extends Controller
{
    public function __construct(
        private readonly AiConversationService $conversations,
    ) {}

    protected function ensureAvailable(): bool
    {
        return $this->conversations->isEnabled() && $this->conversations->isAvailable();
    }

    /**
     * Uniform controlled payload when the AI capability is unavailable.
     */
    protected function unavailable(): JsonResponse
    {
        return response()->json(['message' => AiUnavailableException::SAFE_MESSAGE], 503);
    }

    /**
     * GET /ai/conversations
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->ensureAvailable()) {
            return $this->unavailable();
        }

        $conversations = $this->conversations->listForUser($request->user())->map(
            fn (AiConversation $conversation) => $this->presentConversation($conversation)
        );

        return response()->json(['data' => $conversations]);
    }

    /**
     * GET /ai/conversations/{conversation}
     */
    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        if (! $this->ensureAvailable()) {
            return $this->unavailable();
        }

        $this->conversations->findForUser((int) $conversation->id, $request->user());

        $messages = $this->conversations->messages($conversation)->map(
            fn ($message) => [
                'role' => $message->role->value,
                'content' => $message->content,
                'provider_message_id' => $message->provider_message_id,
                'model' => $message->model,
                'input_tokens' => $message->input_tokens,
                'output_tokens' => $message->output_tokens,
                'total_tokens' => $message->total_tokens,
                'created_at' => $message->created_at?->toISOString(),
            ]
        );

        return response()->json([
            'data' => $this->presentConversation($conversation),
            'messages' => $messages,
        ]);
    }

    /**
     * POST /ai/chat
     *
     * Body: { message: string, title?: string, conversation_id?: int }
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->ensureAvailable()) {
            return $this->unavailable();
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'conversation_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $user = $request->user();
        $conversation = null;

        if (! empty($validated['conversation_id'])) {
            $conversation = $this->conversations->findForUser((int) $validated['conversation_id'], $user);
        }

        if (! $conversation) {
            $orgIds = OrganizationContext::getUserOrganizationIds($user);
            $organizationId = count($orgIds) === 1 ? (int) reset($orgIds) : null;
            $conversation = $this->conversations->create(
                user: $user,
                organizationId: $organizationId,
                title: $validated['title'] ?? null,
            );
        }

        try {
            $response = $this->conversations->send($conversation, $validated['message']);
        } catch (AiUnavailableException) {
            return $this->unavailable();
        }

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
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

    protected function presentConversation(AiConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'status' => $conversation->status->value,
            'provider' => $conversation->provider,
            'model' => $conversation->model,
            'created_at' => $conversation->created_at?->toISOString(),
            'updated_at' => $conversation->updated_at?->toISOString(),
        ];
    }
}