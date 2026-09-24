<?php

namespace App\Http\Controllers;

use App\AI\Exceptions\AiUnavailableException;
use App\AI\Policies\AiToolPolicy;
use App\AI\Services\AiConversationService;
use App\AI\Services\AiGuardrailService;
use App\Models\AiConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minimal internal JSON endpoints for the AI foundation. There is no public
 * API surface here: every route requires an authenticated user with the ai.*
 * permission, and every request passes through AiGuardrailService (which
 * builds the trusted context, applies the default-deny AiToolPolicy, audits
 * the decision, and revalidates conversation ownership/tenant scope).
 *
 * Responses never echo provider internals and always return a controlled 503
 * payload when the AI capability is unavailable.
 */
class AiController extends Controller
{
    public function __construct(
        private readonly AiConversationService $conversations,
        private readonly AiGuardrailService $guardrail,
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

        $this->guardrail->authorize('ai.conversation.list', [], $request->user());

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

        $context = $this->guardrail->authorize(
            'ai.conversation.read',
            ['conversation_id' => (int) $conversation->id],
            $request->user()
        );

        $this->guardrail->checkConversationAccess($conversation, $context);

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
     *
     * Privilege-influencing keys (organization_id, member_id, role, ...) are
     * rejected outright; tenant and member scope can never be supplied by the
     * client — they are always taken from the trusted context.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->ensureAvailable()) {
            return $this->unavailable();
        }

        $rules = [
            'message' => ['required', 'string', 'max:4000'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'conversation_id' => ['sometimes', 'nullable', 'integer'],
        ];

        foreach (AiToolPolicy::FORBIDDEN_ARGUMENT_KEYS as $key) {
            $rules[$key] = 'prohibited';
        }

        $validated = $request->validate($rules);

        $arguments = [];
        if (! empty($validated['conversation_id'])) {
            $arguments['conversation_id'] = (int) $validated['conversation_id'];
        }

        $user = $request->user();
        $context = $this->guardrail->authorize('ai.chat', $arguments, $user);

        $conversation = null;

        if (! empty($validated['conversation_id'])) {
            $conversation = $this->conversations->findForUser((int) $validated['conversation_id'], $user);
            $this->guardrail->checkConversationAccess($conversation, $context);
        }

        if (! $conversation) {
            $organizationId = count($context->organizationIds) === 1
                ? (int) $context->organizationIds[0]
                : null;

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