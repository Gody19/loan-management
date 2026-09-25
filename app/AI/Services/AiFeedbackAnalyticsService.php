<?php

namespace App\AI\Services;

use BackedEnum;
use App\AI\DTOs\AiContextData;
use App\AI\Policies\AiFeedbackPolicy;
use App\Enums\AiEvaluationStatus;
use App\Enums\AiFeedbackStatus;
use App\Enums\AiFeedbackType;
use App\Models\AiEvaluation;
use App\Models\AiFeedback;
use App\Services\AuditService;

/**
 * Aggregate feedback and evaluation metrics.
 *
 * Every figure is derived from a tenant-scoped query, so an organization only
 * ever sees its own numbers and a super administrator sees the platform. The
 * service returns counts only — it never surfaces individual feedback rows to
 * a caller who could not already read them, which keeps an aggregate screen
 * from becoming a privacy bypass.
 *
 * This is descriptive statistics for future evaluation. It makes no claim that
 * one model version performed better than another and drives no automated
 * decision.
 */
class AiFeedbackAnalyticsService
{
    public function __construct(
        private readonly AiFeedbackPolicy $policy,
        private readonly AuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(AiContextData $context): array
    {
        if (! $this->policy->canReview($context)) {
            abort(403, 'Unauthorized to view AI feedback analytics.');
        }

        $feedback = AiFeedback::query();
        $this->policy->scopeForContext($feedback, $context);

        $evaluations = AiEvaluation::query();
        $this->policy->scopeForContext($evaluations, $context);

        $total = (clone $feedback)->count();
        $byType = $this->countsBy(AiFeedback::class, 'type', AiFeedbackType::values(), $context);
        $byStatus = $this->countsBy(AiFeedback::class, 'status', AiFeedbackStatus::values(), $context);

        $approved = (clone $evaluations)->where('status', AiEvaluationStatus::Approved->value)->count();
        $rejected = (clone $evaluations)->where('status', AiEvaluationStatus::Rejected->value)->count();
        $pending = (clone $evaluations)->whereIn('status', [
            AiEvaluationStatus::Pending->value,
            AiEvaluationStatus::InReview->value,
        ])->count();

        $decided = $approved + $rejected;
        $byModel = $this->modelBreakdown($context);
        $this->audit->log('ai.feedback.analytics.viewed', null, [], [
            'feedback_count' => $total,
            'approved_count' => $approved,
            'rejected_count' => $rejected,
            'organization_count' => count($context->organizationIds),
        ]);

        return [
            'total_feedback' => $total,
            'by_type' => $byType,
            'by_status' => $byStatus,
            'approved' => $approved,
            'rejected' => $rejected,
            'pending_evaluations' => $pending,
            'decided_evaluations' => $decided,
            'approval_rate' => $decided > 0 ? round($approved / $decided, 4) : null,
            'rejection_rate' => $decided > 0 ? round($rejected / $decided, 4) : null,
            'by_model' => $byModel,
            'scope' => [
                'is_super_admin' => $context->isSuperAdmin,
                'organization_count' => count($context->organizationIds),
                'branch_count' => count($context->branchIds),
            ],
        ];
    }

    /**
     * Counts for one controlled column, with every declared value present so
     * the UI does not have to guess at zeros.
     *
     * Grouping is done in PHP over an already tenant-scoped, bounded read, so
     * no raw SQL is involved anywhere in this service.
     *
     * @param  list<string>  $values
     * @return array<string, int>
     */
    protected function countsBy(string $model, string $column, array $values, AiContextData $context): array
    {
        $result = array_fill_keys($values, 0);

        $query = $model::query()->limit(10000);
        $this->policy->scopeForContext($query, $context);

        foreach ($query->pluck($column) as $bucket) {
            $key = $bucket instanceof BackedEnum ? $bucket->value : (string) $bucket;

            if (array_key_exists($key, $result)) {
                $result[$key]++;
            }
        }

        return $result;
    }

    /**
     * Feedback volume by the AI model version that produced the response.
     * Recorded as evidence only; no interpretation is attached.
     *
     * @return array<int, array{provider: string|null, model: string|null, feedback_count: int, positive_count: int, negative_count: int, correction_count: int}>
     */
    protected function modelBreakdown(AiContextData $context): array
    {
        $query = AiFeedback::query()->limit(10000);
        $this->policy->scopeForContext($query, $context);

        $buckets = [];

        foreach ($query->get(['provider', 'model', 'type']) as $feedback) {
            $key = ($feedback->provider ?? 'unknown') . '|' . ($feedback->model ?? 'unknown');

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'provider' => $feedback->provider,
                    'model' => $feedback->model,
                    'feedback_count' => 0,
                    'positive_count' => 0,
                    'negative_count' => 0,
                    'correction_count' => 0,
                ];
            }

            $buckets[$key]['feedback_count']++;

            match ($feedback->type) {
                AiFeedbackType::Positive => $buckets[$key]['positive_count']++,
                AiFeedbackType::Negative => $buckets[$key]['negative_count']++,
                AiFeedbackType::Correction => $buckets[$key]['correction_count']++,
                default => null,
            };
        }

        usort(
            $buckets,
            fn (array $a, array $b) => $b['feedback_count'] <=> $a['feedback_count'],
        );

        return array_slice(array_values($buckets), 0, 20);
    }
}
