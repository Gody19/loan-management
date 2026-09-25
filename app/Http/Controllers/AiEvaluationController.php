<?php

namespace App\Http\Controllers;

use App\AI\DTOs\AiContextData;
use App\AI\Policies\AiToolPolicy;
use App\AI\Services\AiContextBuilderService;
use App\AI\Services\AiEvaluationService;
use App\Enums\AiEvaluationCriterion;
use App\Enums\AiEvaluationResult;
use App\Enums\AiEvaluationStatus;
use App\Enums\AiMessageRole;
use App\Models\AiEvaluation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reviewer-facing evaluation endpoints.
 *
 * Reviewing and approving are separate capabilities. Every route is gated by
 * the trusted context rather than by a client-supplied role, and the reviewer
 * identity always comes from the authenticated session — the payload cannot
 * name an evaluator, a status, or a tenant.
 */
class AiEvaluationController extends Controller
{
    public function __construct(
        private readonly AiContextBuilderService $contextBuilder,
        private readonly AiEvaluationService $evaluations,
    ) {}

    /**
     * GET /ai/evaluations/queue — the reviewer console.
     */
    public function queue(Request $request): View
    {
        return view('ai.feedback.review');
    }

    /**
     * GET /ai/evaluations — the tenant-scoped review queue.
     */
    public function index(Request $request): JsonResponse
    {
        $context = $this->contextBuilder->build($request->user());

        $records = $this->evaluations->queue($context)->map(fn (AiFeedback $feedback) => [
            'feedback_id' => $feedback->id,
            'type' => $feedback->type->value,
            'type_label' => $feedback->type->label(),
            'status' => $feedback->status->value,
            'correction' => $feedback->correction,
            'reason' => $feedback->reason,
            'submitted_by' => $feedback->user_id,
            'model' => $feedback->model,
            'provider' => $feedback->provider,
            'question' => $this->questionFor($feedback),
            'response' => $feedback->message?->content,
            'evaluation' => $feedback->evaluation ? [
                'id' => $feedback->evaluation->id,
                'status' => $feedback->evaluation->status->value,
            ] : null,
            'created_at' => $feedback->created_at?->toISOString(),
        ]);

        return response()->json([
            'data' => $records,
            'criteria' => collect(AiEvaluationCriterion::cases())->map(fn (AiEvaluationCriterion $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'description' => $c->description(),
            ])->all(),
        ]);
    }

    /**
     * POST /ai/feedback/{feedback}/evaluation — open the review.
     */
    public function store(Request $request, AiFeedback $feedback): JsonResponse
    {
        $context = $this->contextBuilder->build($request->user());

        $evaluation = $this->evaluations->start($context, $request->user(), $feedback);

        return response()->json(['data' => $this->present($evaluation)], 201);
    }

    /**
     * POST /ai/evaluations/{evaluation} — record the decision.
     *
     * Body: { decision: approved|rejected, scores: {criterion: pass|fail},
     *         notes?: string, rejection_reason?: string,
     *         include_in_dataset?: bool }
     */
    public function update(Request $request, AiEvaluation $evaluation): JsonResponse
    {
        $criteria = AiEvaluationCriterion::values();
        $results = AiEvaluationResult::values();

        $rules = [
            'decision' => ['required', 'string', 'in:'.implode(',', [
                AiEvaluationStatus::Approved->value,
                AiEvaluationStatus::Rejected->value,
            ])],
            'scores' => ['required', 'array'],
            'scores.*' => ['required', 'string', 'in:'.implode(',', $results)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'rejection_reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:500'],
            'include_in_dataset' => ['sometimes', 'boolean'],
        ];

        foreach (AiToolPolicy::FORBIDDEN_ARGUMENT_KEYS as $key) {
            $rules[$key] = 'prohibited';
        }

        foreach (['evaluator_id', 'reviewer_id', 'status', 'dataset_version'] as $key) {
            $rules[$key] = 'prohibited';
        }

        $validated = $request->validate($rules);

        $scores = $this->normalizeScores($validated['scores'], $criteria, $results, $validated['decision']);

        $context = $this->contextBuilder->build($request->user());

        $updated = $this->evaluations->decide(
            $context,
            $request->user(),
            $evaluation,
            (string) $validated['decision'],
            $scores,
            $validated['notes'] ?? null,
            $validated['rejection_reason'] ?? null,
            $request->boolean('include_in_dataset', true),
        );

        return response()->json(['data' => $this->present($updated)]);
    }

    /**
     * The question that produced the evaluated response, for reviewer context.
     */
    protected function questionFor(AiFeedback $feedback): ?string
    {
        $message = $feedback->message;

        if (! $message || $message->ai_conversation_id === null) {
            return null;
        }

        return AiMessage::where('ai_conversation_id', $message->ai_conversation_id)
            ->where('role', AiMessageRole::User->value)
            ->where('id', '<', $message->id)
            ->orderByDesc('id')
            ->value('content');
    }

    /**
     * Only the controlled criteria are accepted, and only the two terminal
     * decisions are allowed. An approval additionally requires every criterion
     * to be present and to have passed, so an approval can never be recorded
     * against an incomplete or failing review.
     *
     * @param  array<string, string>  $scores
     * @param  list<string>  $criteria
     * @param  list<string>  $results
     * @return array<string, string>
     */
    protected function normalizeScores(array $scores, array $criteria, array $results, string $decision): array
    {
        foreach (array_keys($scores) as $criterion) {
            if (! in_array($criterion, $criteria, true)) {
                abort(422, "Unknown evaluation criterion: {$criterion}.");
            }
        }

        $normalized = [];

        foreach ($scores as $criterion => $result) {
            if (! in_array((string) $result, $results, true)) {
                abort(422, "Unknown evaluation result for {$criterion}.");
            }

            $normalized[$criterion] = (string) $result;
        }

        if ($decision === AiEvaluationStatus::Approved->value) {
            foreach ($criteria as $criterion) {
                if (! isset($normalized[$criterion])) {
                    abort(422, "An approval requires a result for every criterion. Missing: {$criterion}.");
                }
            }

            foreach ($normalized as $criterion => $result) {
                if ($result !== AiEvaluationResult::Pass->value) {
                    abort(422, "Cannot approve while {$criterion} is failing.");
                }
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(AiEvaluation $evaluation): array
    {
        return [
            'id' => $evaluation->id,
            'feedback_id' => $evaluation->ai_feedback_id,
            'message_id' => $evaluation->ai_message_id,
            'status' => $evaluation->status->value,
            'status_label' => $evaluation->status->label(),
            'scores' => $evaluation->scores ?? [],
            'notes' => $evaluation->notes,
            'rejection_reason' => $evaluation->rejection_reason,
            'reviewed_at' => $evaluation->reviewed_at?->toISOString(),
            'example' => $evaluation->learningExample ? [
                'id' => $evaluation->learningExample->id,
                'dataset_version' => $evaluation->learningExample->dataset_version,
                'status' => $evaluation->learningExample->status->value,
            ] : null,
        ];
    }
}
