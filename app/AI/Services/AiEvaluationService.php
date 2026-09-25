<?php

namespace App\AI\Services;

use App\AI\DTOs\AiContextData;
use App\AI\Policies\AiFeedbackPolicy;
use App\Enums\AiEvaluationStatus;
use App\Enums\AiFeedbackStatus;
use App\Enums\AiMessageRole;
use App\Models\AiEvaluation;
use App\Models\AiFeedback;
use App\Models\AiLearningExample;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The human review workflow between feedback and the learning dataset.
 *
 * This is the only automation the phase permits: a person with the explicit
 * review/approve capability decides whether an example is good enough to
 * learn from. Reviewers act on tenant-scoped records, may not review their own
 * submitted feedback into the dataset, and cannot transition an evaluation that
 * has already reached a terminal state.
 *
 * Concurrent reviewers are serialized with a row lock, and the approve path
 * marks the evaluation approved, sanitizes the source, and writes the dataset
 * example in a single transaction. A failure anywhere rolls the whole decision
 * back, so the system never shows an approval that produced no example.
 */
class AiEvaluationService
{
    public function __construct(
        private readonly AiFeedbackPolicy $policy,
        private readonly AiLearningDatasetService $dataset,
        private readonly AuditService $audit,
    ) {}

    /**
     * The review queue for the trusted context: open feedback scoped to the
     * caller's organization (and branch, when branch scoped).
     *
     * @return Collection<int, AiFeedback>
     */
    public function queue(AiContextData $context): Collection
    {
        $query = AiFeedback::query()
            ->with(['evaluation', 'message', 'user'])
            ->orderBy('id');

        $this->policy->scopeForContext($query, $context);

        return $query->open()->get();
    }

    /**
     * Open an evaluation for a piece of feedback. Idempotent: an existing
     * non-terminal evaluation is returned rather than duplicated.
     */
    public function start(AiContextData $context, User $reviewer, AiFeedback $feedback): AiEvaluation
    {
        $this->authorizeReview($context, $reviewer, $feedback);

        return DB::transaction(function () use ($reviewer, $feedback) {
            $locked = AiFeedback::query()->whereKey($feedback->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === AiFeedbackStatus::Withdrawn
                || $locked->status === AiFeedbackStatus::Reviewed) {
                abort(409, 'This feedback is no longer available for review.');
            }

            $existing = AiEvaluation::query()->where('ai_feedback_id', $locked->id)->lockForUpdate()->first();

            if ($existing) {
                if (! $existing->status->isTerminal()) {
                    $this->markInReview($existing, $locked);

                    return $existing;
                }

                abort(409, 'This feedback has already been finalized.');
            }

            $evaluation = AiEvaluation::create([
                'ai_feedback_id' => $locked->id,
                'ai_message_id' => $locked->ai_message_id,
                'ai_conversation_id' => $locked->ai_conversation_id,
                'evaluator_id' => $reviewer->id,
                'organization_id' => $locked->organization_id,
                'branch_id' => $locked->branch_id,
                'status' => AiEvaluationStatus::InReview,
            ]);

            $locked->update(['status' => AiFeedbackStatus::InReview]);

            $this->audit->log('ai.evaluation.created', $evaluation, [], $this->auditMetadata($evaluation));

            return $evaluation;
        });
    }

    /**
     * Record a reviewer's decision.
     *
     * @param  array<string, string>  $scores  criterion => pass|fail
     * @param  bool  $includeInDataset  whether the reviewer wants a dataset example
     */
    public function decide(
        AiContextData $context,
        User $reviewer,
        AiEvaluation $evaluation,
        string $decision,
        array $scores,
        ?string $notes = null,
        ?string $rejectionReason = null,
        bool $includeInDataset = true,
    ): AiEvaluation {
        $feedback = $evaluation->feedback;

        if (! $feedback) {
            abort(404, 'Evaluation source feedback not found.');
        }

        $this->authorizeReview($context, $reviewer, $feedback);

        $allowedDecisions = [
            AiEvaluationStatus::Approved->value,
            AiEvaluationStatus::Rejected->value,
        ];

        if (! in_array($decision, $allowedDecisions, true)) {
            abort(422, 'Invalid AI evaluation decision.');
        }

        if ($decision === AiEvaluationStatus::Rejected->value && trim((string) $rejectionReason) === '') {
            abort(422, 'A rejection reason is required.');
        }

        $isApproval = $decision === AiEvaluationStatus::Approved->value;

        if ($isApproval && ! $this->policy->canApprove($context)) {
            abort(403, 'Unauthorized to approve AI evaluations.');
        }

        $example = null;

        DB::transaction(function () use ($reviewer, $evaluation, $feedback, $isApproval, $scores, $notes, $rejectionReason, $includeInDataset, &$example) {
            // Serialize competing reviewers on this row. The lock is what makes
            // "reviewer A approves while reviewer B rejects" resolve to one
            // authoritative state instead of two.
            $locked = AiEvaluation::where('id', $evaluation->id)->lockForUpdate()->firstOrFail();

            if (! $this->policy->canTransition($locked)) {
                abort(409, 'This evaluation has already been finalized.');
            }

            $currentFeedback = AiFeedback::query()->whereKey($feedback->id)->lockForUpdate()->first();

            if (! $currentFeedback
                || $currentFeedback->status === AiFeedbackStatus::Withdrawn
                || $currentFeedback->status === AiFeedbackStatus::Reviewed) {
                abort(409, 'This feedback has been withdrawn and is no longer available for evaluation.');
            }

            $locked->update([
                'status' => $isApproval
                    ? AiEvaluationStatus::Approved
                    : AiEvaluationStatus::Rejected,
                'evaluator_id' => $reviewer->id,
                'scores' => $scores,
                'notes' => $notes,
                'rejection_reason' => $isApproval ? null : $rejectionReason,
                'reviewed_at' => now(),
            ]);

            if ($isApproval && $includeInDataset) {
                $example = $this->dataset->createFromEvaluation(
                    $locked,
                    $reviewer,
                    $this->inputTextFor($feedback),
                    $this->responseTextFor($feedback),
                    $feedback->correction,
                )['example'];
            }

            $currentFeedback->update(['status' => AiFeedbackStatus::Reviewed]);

            $this->audit->log(
                $isApproval ? 'ai.evaluation.approved' : 'ai.evaluation.rejected',
                $locked,
                ['status' => $evaluation->status->value],
                $this->auditMetadata($locked, $example),
            );

            $this->audit->log('ai.evaluation.reviewed', $locked, [], $this->auditMetadata($locked, $example));
        });

        $evaluation->refresh();

        return $evaluation;
    }

    /**
     * Move an evaluation back to in_review without deciding it. Used when a
     * reviewer opens the detail screen.
     */
    public function markInReview(AiEvaluation $evaluation, AiFeedback $feedback): AiEvaluation
    {
        if ($evaluation->status === AiEvaluationStatus::InReview) {
            return $evaluation;
        }

        $evaluation->update(['status' => AiEvaluationStatus::InReview]);

        $this->audit->log('ai.evaluation.reviewed', $evaluation, [], $this->auditMetadata($evaluation));

        return $evaluation;
    }

    /**
     * Reviewer authorization: review capability, tenant record scope, and no
     * self-review into the dataset. A reviewer may still look at their own
     * feedback through the owner path, but approving their own correction for
     * training would defeat the point of human review.
     */
    protected function authorizeReview(AiContextData $context, User $reviewer, AiFeedback $feedback): void
    {
        if (! $this->policy->canReview($context)) {
            abort(403, 'Unauthorized to review AI feedback.');
        }

        if ((int) $feedback->user_id === (int) $reviewer->id) {
            abort(403, 'A feedback submitter cannot review their own feedback.');
        }

        if (! $this->policy->withinTenantScope($context, $feedback)) {
            abort(403, 'Unauthorized AI feedback record.');
        }
    }

    /**
     * The user's question that produced the evaluated response. Taken from the
     * closest preceding user message so the example keeps its input/output
     * pairing. Falls back to an empty string, which sanitization then refuses
     * rather than inventing a prompt.
     */
    protected function inputTextFor(AiFeedback $feedback): string
    {
        $message = $feedback->message;

        if (! $message) {
            return '';
        }

        $userMessage = AiMessage::where('ai_conversation_id', $message->ai_conversation_id)
            ->where('role', AiMessageRole::User->value)
            ->where('id', '<', $message->id)
            ->orderByDesc('id')
            ->first();

        return (string) ($userMessage?->content ?? '');
    }

    protected function responseTextFor(AiFeedback $feedback): string
    {
        return (string) ($feedback->message?->content ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditMetadata(AiEvaluation $evaluation, ?AiLearningExample $example = null): array
    {
        $metadata = [
            'evaluation_id' => $evaluation->id,
            'feedback_id' => $evaluation->ai_feedback_id,
            'message_id' => $evaluation->ai_message_id,
            'conversation_id' => $evaluation->ai_conversation_id,
            'evaluator_id' => $evaluation->evaluator_id,
            'organization_id' => $evaluation->organization_id,
            'status' => $evaluation->status->value,
        ];

        if ($example) {
            $metadata['example_id'] = $example->id;
            $metadata['dataset_version'] = $example->dataset_version;
        }

        return $metadata;
    }
}
