<?php

namespace App\AI\Services;

use App\AI\DTOs\AiContextData;
use App\AI\Policies\AiFeedbackPolicy;
use App\Enums\AiLearningExampleStatus;
use App\Models\AiEvaluation;
use App\Models\AiLearningExample;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Collection;

/**
 * The approved learning dataset — the export boundary for this phase.
 *
 * An example is created only when an authorized reviewer approves an
 * evaluation, and only from text that has already been sanitized. The dataset
 * holds sanitized snapshots rather than live message content, so it does not
 * keep the original private conversation text alive after that text is pruned.
 *
 * Approved examples are immutable. Correcting one supersedes it and points at
 * the replacement rather than mutating history, and every example is traceable
 * to the evaluation and feedback that produced it.
 *
 * Nothing in this service trains, fine-tunes, deploys, or selects a model.
 */
class AiLearningDatasetService
{
    public function __construct(
        private readonly AiDatasetSanitizerService $sanitizer,
        private readonly AiFeedbackPolicy $policy,
        private readonly AuditService $audit,
    ) {}

    /**
     * Create the sanitized dataset example for an approved evaluation.
     *
     * Callers must run this inside the same transaction that marks the
     * evaluation approved, so an approval can never exist without its example.
     *
     * @return array{example: AiLearningExample, report: array<string, int>}
     */
    public function createFromEvaluation(
        AiEvaluation $evaluation,
        User $approver,
        string $inputText,
        string $originalResponse,
        ?string $correctedResponse,
    ): array {
        $existing = AiLearningExample::where('ai_evaluation_id', $evaluation->id)->first();

        if ($existing) {
            // The unique index guarantees one example per evaluation; a
            // duplicate approval attempt is a no-op, never a second row.
            return ['example' => $existing, 'report' => $existing->sanitization_report ?? []];
        }

        $sanitized = $this->sanitizer->sanitizeExample($inputText, $originalResponse, $correctedResponse);

        if (! $sanitized['safe']) {
            // Fail loudly instead of exporting something the reviewer has not
            // actually seen in redacted form.
            abort(422, 'This example cannot be sanitized safely. Reject it from the dataset instead of approving it.');
        }

        $example = AiLearningExample::create([
            'ai_evaluation_id' => $evaluation->id,
            'ai_feedback_id' => $evaluation->ai_feedback_id,
            'organization_id' => $evaluation->organization_id,
            'branch_id' => $evaluation->branch_id,
            'dataset_version' => $this->nextVersionFor($evaluation),
            'status' => AiLearningExampleStatus::Active,
            'input_text' => $sanitized['input_text'],
            'original_response' => $sanitized['original_response'],
            'corrected_response' => $sanitized['corrected_response'],
            'evaluation_metadata' => $this->evaluationMetadata($evaluation),
            'sanitization_report' => $sanitized['report'],
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->audit->log('ai.dataset.example.approved', $example, [], [
            'example_id' => $example->id,
            'evaluation_id' => $evaluation->id,
            'feedback_id' => $evaluation->ai_feedback_id,
            'organization_id' => $example->organization_id,
            'dataset_version' => $example->dataset_version,
            'status' => $example->status->value,
            'redacted_categories' => array_keys($sanitized['report']),
        ]);

        return ['example' => $example, 'report' => $sanitized['report']];
    }

    /**
     * Mark an existing example superseded by its replacement, keeping the
     * original row for audit.
     */
    public function supersede(AiLearningExample $example, AiLearningExample $replacement): AiLearningExample
    {
        $example->update([
            'status' => AiLearningExampleStatus::Superseded,
            'superseded_by_id' => $replacement->id,
        ]);

        $this->audit->log('ai.dataset.example.superseded', $example, [], [
            'example_id' => $example->id,
            'superseded_by_id' => $replacement->id,
            'dataset_version' => $example->dataset_version,
        ]);

        return $example->fresh() ?? $example;
    }

    /**
     * Withdraw an example from the dataset without deleting history. Used when
     * a reviewer discovers after approval that the content was unsafe.
     */
    public function revoke(AiLearningExample $example): AiLearningExample
    {
        $example->update(['status' => AiLearningExampleStatus::Revoked]);

        $this->audit->log('ai.dataset.example.revoked', $example, [], [
            'example_id' => $example->id,
            'dataset_version' => $example->dataset_version,
        ]);

        return $example->fresh() ?? $example;
    }

    /**
     * Active examples the trusted context may export, optionally pinned to one
     * dataset version. Only approved/active rows qualify; superseded and
     * revoked rows are structurally excluded.
     *
     * @return Collection<int, AiLearningExample>
     */
    public function exportable(AiContextData $context, ?int $version = null): Collection
    {
        $query = AiLearningExample::query()
            ->where('status', AiLearningExampleStatus::Active->value)
            ->orderBy('dataset_version')
            ->orderBy('id');

        $this->policy->scopeForContext($query, $context);

        if ($version !== null) {
            $query->where('dataset_version', $version);
        }

        return $query->get();
    }

    /**
     * Examples the trusted context may browse, including superseded and
     * revoked rows so reviewers can see the full history.
     *
     * @return Collection<int, AiLearningExample>
     */
    public function listForContext(AiContextData $context, ?int $version = null): Collection
    {
        $query = AiLearningExample::query()->orderByDesc('id');

        $this->policy->scopeForContext($query, $context);

        if ($version !== null) {
            $query->where('dataset_version', $version);
        }

        return $query->get();
    }

    /**
     * Deterministic dataset version: the next integer within the evaluation's
     * own tenant scope. Versions are per-tenant, so one organization can never
     * observe or collide with another organization's numbering.
     */
    protected function nextVersionFor(AiEvaluation $evaluation): int
    {
        $query = AiLearningExample::query()
            ->where('organization_id', $evaluation->organization_id)
            ->where('branch_id', $evaluation->branch_id);

        return (int) $query->lockForUpdate()->max('dataset_version') + 1;
    }

    /**
     * Evaluation provenance attached to each example. Contains criteria
     * outcomes and the sanitization category counts only — never the raw
     * feedback text.
     *
     * @return array<string, mixed>
     */
    protected function evaluationMetadata(AiEvaluation $evaluation): array
    {
        return [
            'evaluation_id' => $evaluation->id,
            'feedback_id' => $evaluation->ai_feedback_id,
            'status' => $evaluation->status->value,
            'scores' => $evaluation->scores ?? [],
            'evaluator_id' => $evaluation->evaluator_id,
            'reviewed_at' => $evaluation->reviewed_at?->toISOString(),
        ];
    }
}
