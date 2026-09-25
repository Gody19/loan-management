<?php

namespace App\Http\Controllers;

use App\AI\Policies\AiFeedbackPolicy;
use App\AI\Services\AiContextBuilderService;
use App\AI\Services\AiDatasetExportService;
use App\AI\Services\AiFeedbackAnalyticsService;
use App\AI\Services\AiLearningDatasetService;
use App\Models\AiLearningExample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Reviewer-facing approved-dataset and export endpoints.
 *
 * Browsing the dataset and exporting it are separate capabilities. Export is
 * deliberately a JSONL attachment produced on demand, not a public API and not
 * a stored artifact, so there is no URL that can be shared to leak it.
 */
class AiLearningDatasetController extends Controller
{
    public function __construct(
        private readonly AiContextBuilderService $contextBuilder,
        private readonly AiLearningDatasetService $dataset,
        private readonly AiDatasetExportService $exports,
        private readonly AiFeedbackAnalyticsService $analytics,
        private readonly AiFeedbackPolicy $policy,
    ) {}

    /**
     * GET /ai/dataset — approved examples in the caller's scope.
     */
    public function index(Request $request): JsonResponse
    {
        $context = $this->contextBuilder->build($request->user());

        $version = $request->integer('version') ?: null;

        $records = $this->dataset->listForContext($context, $version)->map(
            fn (AiLearningExample $example) => $this->present($example)
        );

        return response()->json(['data' => $records]);
    }

    /**
     * GET /ai/dataset/analytics — tenant-scoped aggregate metrics.
     */
    public function analytics(Request $request): JsonResponse
    {
        $context = $this->contextBuilder->build($request->user());

        return response()->json(['data' => $this->analytics->summary($context)]);
    }

    /**
     * GET /ai/dataset/export — JSONL download of approved, sanitized examples.
     */
    public function export(Request $request): Response
    {
        $context = $this->contextBuilder->build($request->user());

        $version = $request->integer('version') ?: null;

        $export = $this->exports->toJsonl($context, $version);

        $filename = 'ai-learning-dataset'
            . ($version !== null ? "-v{$version}" : '')
            . '.jsonl';

        return response($export['body'], 200, [
            'Content-Type' => 'application/x-ndjson; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-FinancePro-Example-Count' => (string) $export['count'],
        ]);
    }

    /**
     * POST /ai/dataset/examples/{example}/revoke — remove an example from the
     * dataset without deleting its history.
     */
    public function revoke(Request $request, AiLearningExample $example): JsonResponse
    {
        $context = $this->contextBuilder->build($request->user());

        if (! $this->policy->canApprove($context) || ! $this->policy->withinTenantScope($context, $example)) {
            abort(403, 'Unauthorized AI learning example.');
        }

        $updated = $this->dataset->revoke($example);

        return response()->json(['data' => $this->present($updated)]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(AiLearningExample $example): array
    {
        return [
            'id' => $example->id,
            'evaluation_id' => $example->ai_evaluation_id,
            'feedback_id' => $example->ai_feedback_id,
            'dataset_version' => $example->dataset_version,
            'status' => $example->status->value,
            'status_label' => $example->status->label(),
            'input_text' => $example->input_text,
            'original_response' => $example->original_response,
            'corrected_response' => $example->corrected_response,
            'scores' => $example->evaluation_metadata['scores'] ?? [],
            'redacted_categories' => array_keys($example->sanitization_report ?? []),
            'approved_at' => $example->approved_at?->toISOString(),
        ];
    }
}
