<?php

namespace App\Http\Controllers;

use App\AI\DTOs\AiContextData;
use App\AI\Policies\AiFeedbackPolicy;
use App\AI\Policies\AiToolPolicy;
use App\AI\Services\AiContextBuilderService;
use App\AI\Services\AiFeedbackService;
use App\Enums\AiFeedbackType;
use App\Models\AiFeedback;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User-facing feedback endpoints.
 *
 * Any AI user may react to a response in a conversation they can already
 * read. The controller does authorization, validation, and shaping only — all
 * tenant derivation and persistence live in AiFeedbackService, and no value
 * that identifies a user, message, or tenant is ever taken from the request.
 */
class AiFeedbackController extends Controller
{
    public function __construct(
        private readonly AiContextBuilderService $contextBuilder,
        private readonly AiFeedbackService $feedback,
        private readonly AiFeedbackPolicy $policy,
    ) {}

    /**
     * GET /ai/feedback — the caller's own feedback records.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $records = $this->feedback->listForUser($user)->map(fn (AiFeedback $feedback) => $this->present($feedback));

        return response()->json(['data' => $records]);
    }

    /**
     * GET /ai/feedback/{feedback} — one record, owner or in-scope reviewer.
     */
    public function show(Request $request, AiFeedback $feedback): JsonResponse
    {
        $context = $this->contextBuilder->build($request->user());

        if (! $this->policy->canViewFeedback($context, $request->user(), $feedback)) {
            abort(403, 'Unauthorized AI feedback record.');
        }

        return response()->json(['data' => $this->present($feedback)]);
    }

    /**
     * POST /ai/feedback
     *
     * Body: { message_id: int, type: positive|negative|correction,
     *         correction?: string, reason?: string }
     *
     * Repeated submissions for the same message update the existing record
     * rather than creating duplicates.
     */
    public function store(Request $request): JsonResponse
    {
        $rules = [
            'message_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:'.implode(',', AiFeedbackType::values())],
            'correction' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];

        foreach (AiToolPolicy::FORBIDDEN_ARGUMENT_KEYS as $key) {
            $rules[$key] = 'prohibited';
        }

        foreach (['status', 'ai_message_id', 'ai_conversation_id'] as $key) {
            $rules[$key] = 'prohibited';
        }

        $validated = $request->validate($rules);

        $context = $this->contextBuilder->build($request->user());

        $result = $this->feedback->submit(
            $context,
            $request->user(),
            (int) $validated['message_id'],
            AiFeedbackType::from($validated['type']),
            $validated['correction'] ?? null,
            $validated['reason'] ?? null,
        );

        return response()->json([
            'data' => $this->present($result['feedback']),
        ], $result['created'] ? 201 : 200);
    }

    /**
     * POST /ai/feedback/{feedback}/withdraw
     */
    public function withdraw(Request $request, AiFeedback $feedback): JsonResponse
    {
        $context = $this->contextBuilder->build($request->user());

        $updated = $this->feedback->withdraw($context, $request->user(), $feedback);

        return response()->json(['data' => $this->present($updated)]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(AiFeedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'message_id' => $feedback->ai_message_id,
            'conversation_id' => $feedback->ai_conversation_id,
            'type' => $feedback->type->value,
            'type_label' => $feedback->type->label(),
            'status' => $feedback->status->value,
            'status_label' => $feedback->status->label(),
            'correction' => $feedback->correction,
            'reason' => $feedback->reason,
            'model' => $feedback->model,
            'provider' => $feedback->provider,
            'evaluation' => $feedback->evaluation ? [
                'id' => $feedback->evaluation->id,
                'status' => $feedback->evaluation->status->value,
                'status_label' => $feedback->evaluation->status->label(),
            ] : null,
            'created_at' => $feedback->created_at?->toISOString(),
            'updated_at' => $feedback->updated_at?->toISOString(),
        ];
    }
}
