<?php

namespace App\AI\Services;

use App\AI\DTOs\AiContextData;
use App\AI\Policies\AiFeedbackPolicy;
use App\Models\AiLearningExample;
use App\Services\AuditService;
use Illuminate\Support\Collection;

/**
 * Secure, permission-gated export of the approved learning dataset.
 *
 * This is the terminal boundary of Phase 11.6. Export is opt-in, human-run,
 * and read-only: it emits approved, sanitized, tenant-authorized examples as
 * JSONL and records who exported what. It does not train, fine-tune, deploy,
 * or register any model, and it is not a public API.
 */
class AiDatasetExportService
{
    public function __construct(
        private readonly AiLearningDatasetService $dataset,
        private readonly AiFeedbackPolicy $policy,
        private readonly AuditService $audit,
    ) {}

    /**
     * Render the exportable dataset as JSON Lines.
     *
     * @return array{body: string, count: int, version: int|null}
     */
    public function toJsonl(AiContextData $context, ?int $version = null): array
    {
        if (! $this->policy->canExport($context)) {
            abort(403, 'Unauthorized to export the AI learning dataset.');
        }

        $examples = $this->dataset->exportable($context, $version);

        $lines = [];

        foreach ($examples as $example) {
            $lines[] = json_encode($this->row($example), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $body = $lines === [] ? '' : implode("\n", $lines) . "\n";

        $this->audit->log('ai.dataset.exported', null, [], [
            'example_count' => $examples->count(),
            'dataset_version' => $version,
            'organization_count' => count($context->organizationIds),
            'format' => 'jsonl',
        ]);

        return [
            'body' => $body,
            'count' => $examples->count(),
            'version' => $version,
        ];
    }

    /**
     * The exported row shape. It intentionally carries only sanitized learning
     * text plus provenance identifiers — never member ids, message content
     * that was redacted, credentials, or internal authorization data.
     *
     * @return array<string, mixed>
     */
    protected function row(AiLearningExample $example): array
    {
        return [
            'id' => $example->id,
            'dataset_version' => $example->dataset_version,
            'input_text' => $example->input_text,
            'original_response' => $example->original_response,
            'corrected_response' => $example->corrected_response,
            'evaluation' => [
                'evaluation_id' => $example->ai_evaluation_id,
                'criteria' => $example->evaluation_metadata['scores'] ?? [],
            ],
            'sanitization' => [
                'redacted_categories' => array_keys($example->sanitization_report ?? []),
            ],
            'approved_at' => $example->approved_at?->toISOString(),
        ];
    }
}
