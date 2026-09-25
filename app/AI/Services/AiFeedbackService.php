<?php

namespace App\AI\Services;

use App\AI\DTOs\AiContextData;
use App\AI\Policies\AiFeedbackPolicy;
use App\Enums\AiFeedbackStatus;
use App\Enums\AiFeedbackType;
use App\Enums\AiMessageRole;
use App\Models\AiEvaluation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\AiModelVersion;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Submission and lifecycle of user feedback on AI responses.
 *
 * Feedback is untrusted user data. It is stored as data, never executed, and
 * never written back to any FinancePro business record — a correction saying
 * "the balance is wrong" does not change a balance anywhere. The only route
 * from feedback to anything else is a human evaluation, which is a separate
 * capability owned by AiEvaluationService.
 *
 * Tenant and ownership values are always derived from the referenced
 * conversation, never from the request payload.
 */
class AiFeedbackService
{
    public function __construct(
        private readonly AiFeedbackPolicy $policy,
        private readonly AuditService $audit,
    ) {}

    /**
     * Create or update the acting user's feedback for one AI message.
     *
     * Idempotent by construction: the unique (ai_message_id, user_id) index
     * means a repeated button click updates the existing record rather than
     * duplicating it. Returns the record together with whether it was created
     * so the caller can pick the right audit event.
     *
     * @return array{feedback: AiFeedback, created: bool}
     */
    public function submit(
        AiContextData $context,
        User $user,
        int $messageId,
        AiFeedbackType $type,
        ?string $correction = null,
        ?string $reason = null,
    ): array {
        if (! $this->policy->canSubmit($context)) {
            abort(403, 'Unauthorized to submit AI feedback.');
        }

        $message = AiMessage::with('conversation')->find($messageId);

        if (! $message) {
            abort(404, 'AI message not found.');
        }

        // Feedback is a reaction to an AI response, not to the user's own
        // prompt or an internal system message.
        if ($message->role !== AiMessageRole::Assistant) {
            abort(422, 'Feedback can only be submitted for an AI response.');
        }

        if (! $this->policy->canSubmitFeedback($context, $user, $message)) {
            abort(403, 'Unauthorized access to this AI message.');
        }

        $conversation = $message->conversation;

        $existing = AiFeedback::where('ai_message_id', $message->id)
            ->where('user_id', $user->id)
            ->first();

        $attributes = [
            'type' => $type,
            'status' => AiFeedbackStatus::Submitted,
            'correction' => $type->requiresCorrection() ? $correction : null,
            'reason' => $reason,
        ];

        if ($existing) {
            // A reviewed record stays reviewed; reopening it would silently
            // undo a human decision.
            if ($existing->status !== AiFeedbackStatus::Reviewed) {
                $existing->update($attributes);
            }

            $this->audit->log('ai.feedback.updated', $existing, [], $this->auditMetadata($existing));

            return ['feedback' => $existing->fresh() ?? $existing, 'created' => false];
        }

        $feedback = DB::transaction(fn () => AiFeedback::create(array_merge($attributes, [
            'ai_message_id' => $message->id,
            'ai_conversation_id' => $conversation?->id,
            'user_id' => $user->id,
            // Tenant columns are copied from the conversation, never accepted
            // from the client.
            'organization_id' => $conversation?->organization_id,
            'branch_id' => $conversation?->branch_id,
            'provider' => $conversation?->provider,
            'model' => $message->model ?? $conversation?->model,
            'ai_model_version_id' => $this->resolveModelVersion($conversation?->provider, $message->model ?? $conversation?->model),
        ])));

        $this->audit->log('ai.feedback.created', $feedback, [], $this->auditMetadata($feedback));

        return ['feedback' => $feedback, 'created' => true];
    }

    /**
     * Withdraw (soft-delete) the acting user's own feedback. The row is kept
     * so the audit trail and any evaluation history stay traceable, and a
     * withdrawn record can never become a dataset example.
     */
    public function withdraw(AiContextData $context, User $user, AiFeedback $feedback): AiFeedback
    {
        if (! $this->policy->canWithdraw($context, $user, $feedback)) {
            abort(403, 'Unauthorized to withdraw this feedback.');
        }

        if (AiEvaluation::where('ai_feedback_id', $feedback->id)->exists()) {
            abort(409, 'Feedback that has entered human review cannot be withdrawn.');
        }

        $feedback->update(['status' => AiFeedbackStatus::Withdrawn]);

        $this->audit->log('ai.feedback.deleted', $feedback, [], $this->auditMetadata($feedback));

        return $feedback->fresh() ?? $feedback;
    }

    /**
     * Feedback the acting user submitted themselves.
     */
    public function listForUser(User $user)
    {
        return AiFeedback::where('user_id', $user->id)
            ->with('evaluation')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Resolve the AI model version that produced the response, when the
     * registered version catalogue knows that provider/model pair. This only
     * records evidence for future comparison; it never selects or changes a
     * model.
     */
    protected function resolveModelVersion(?string $provider, ?string $model): ?int
    {
        if ($provider === null || $model === null || $model === '') {
            return null;
        }

        return AiModelVersion::where('provider', $provider)
            ->where('model', $model)
            ->value('id');
    }

    /**
     * Safe audit metadata only: identifiers, type and status. The correction
     * and reason text is never written to the audit trail.
     *
     * @return array<string, mixed>
     */
    protected function auditMetadata(AiFeedback $feedback): array
    {
        return [
            'feedback_id' => $feedback->id,
            'message_id' => $feedback->ai_message_id,
            'conversation_id' => $feedback->ai_conversation_id,
            'user_id' => $feedback->user_id,
            'type' => $feedback->type->value,
            'status' => $feedback->status->value,
            'model' => $feedback->model,
            'provider' => $feedback->provider,
        ];
    }
}
