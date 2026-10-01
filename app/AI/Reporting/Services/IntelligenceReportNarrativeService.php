<?php

namespace App\AI\Reporting\Services;

use App\AI\DTOs\AiMessageData;
use App\AI\DTOs\AiRequestData;
use App\AI\Services\AiProviderService;
use App\Enums\AiMessageRole;
use App\Enums\ReportDatumClassification;
use App\Services\AuditService;
use Throwable;

/**
 * The optional Phase 12.0 AI narrative layer.
 *
 * The provider receives a sanitized, structured report context — the
 * classified facts, trends, predictive signals and proactive insights of one
 * report — and nothing else. It has no database access, no tool access and no
 * ability to change a figure, a prediction, an insight or any FinancePro
 * record. The narrative is an explanation of what the deterministic reporting
 * service already computed.
 *
 * Failure is always graceful: if the provider is unavailable, errors or returns
 * nothing usable, the report is still delivered and the narrative is simply
 * reported as unavailable. This is why the layer runs after the report exists
 * and why it can never destroy one.
 */
class IntelligenceReportNarrativeService
{
    public const SYSTEM_GUIDANCE = 'You are explaining a FinancePro management intelligence report. '
        .'The report data below is authoritative, deterministic FinancePro output that has already been '
        .'computed by the reporting service. Explain and synthesize it only. '
        .'Never invent, recompute, round differently or contradict any figure, and never state that a figure '
        .'that is absent is zero. Clearly distinguish an observed fact, a trend, a prediction and an advisory '
        .'recommendation, and keep those four labels. A prediction is a statistical indication, never a '
        .'guarantee. An advisory is a suggestion for a human decision; you must never state that an action is '
        .'required, approved or taken, and you must never approve or reject a loan, change a rate, a schedule, '
        .'a balance, an accounting entry or a permission. If the data is insufficient, say so. '
        .'Produce plain prose with these headings: Executive summary, Significant changes, Predictive signals, '
        .'Proactive insights, Areas requiring management attention.';

    /**
     * The four classification groups a section is stored under, keyed by the
     * singular classification so the serialized plural group key and the
     * classification vocabulary can never drift apart.
     *
     * @var array<string, string>
     */
    public const CLASSIFICATION_GROUPS = [
        ReportDatumClassification::Fact->value => 'REPORT FACTS',
        ReportDatumClassification::Trend->value => 'REPORT TRENDS',
        ReportDatumClassification::Prediction->value => 'PREDICTIVE SIGNALS',
        ReportDatumClassification::Advisory->value => 'PROACTIVE INSIGHTS',
    ];

    public function __construct(
        private readonly AiProviderService $providers,
        private readonly AuditService $audit,
    ) {}

    /**
     * Generate the narrative for an already-built report.
     *
     * @param  array<string, mixed>  $reportData
     * @return array{available: bool, summary: ?string, headings: array<string, string>, provider: ?string, model: ?string, reason: ?string}
     */
    public function generate(array $reportData): array
    {
        $unavailable = [
            'available' => false,
            'summary' => null,
            'headings' => [],
            'provider' => null,
            'model' => null,
            'reason' => 'The AI narrative was not requested.',
        ];

        if (! (bool) config('intelligence-reporting.narrative.enabled', true)) {
            $unavailable['reason'] = 'The AI narrative layer is disabled.';

            return $unavailable;
        }

        if (! $this->providers->isAvailable()) {
            $unavailable['reason'] = 'The AI narrative provider is unavailable.';

            return $unavailable;
        }

        try {
            $response = $this->providers->generate(new AiRequestData(
                messages: [
                    new AiMessageData(AiMessageRole::System, self::SYSTEM_GUIDANCE),
                    new AiMessageData(AiMessageRole::User, $this->sanitizedContext($reportData)),
                ],
                model: $this->providers->defaultModel(),
                temperature: (float) config('ai.temperature', 0.7),
                maxOutputTokens: (int) config('ai.max_output_tokens', 1024),
                metadata: ['purpose' => 'intelligence_report_narrative', 'report_type' => $reportData['report_type'] ?? null],
            ));
        } catch (Throwable) {
            // Provider failure must never destroy the authoritative report.
            return [
                'available' => false,
                'summary' => null,
                'headings' => [],
                'provider' => null,
                'model' => null,
                'reason' => 'The AI narrative could not be generated. The financial report is unaffected.',
            ];
        }

        $content = trim((string) $response->content);

        if ($content === '') {
            return [
                'available' => false,
                'summary' => null,
                'headings' => [],
                'provider' => $response->provider,
                'model' => $response->model,
                'reason' => 'The AI narrative provider returned no content.',
            ];
        }

        $this->audit->log('ai.report.narrative_generated', null, [], [
            'report_type' => $reportData['report_type'] ?? null,
            'organization_id' => data_get($reportData, 'scope.organization_id'),
            'provider' => $response->provider,
            'model' => $response->model,
        ]);

        return [
            'available' => true,
            'summary' => $content,
            'headings' => [],
            'provider' => $response->provider,
            'model' => $response->model,
            'reason' => null,
        ];
    }

    /**
     * The sanitized report context handed to the provider.
     *
     * Only the classified report dataset travels: a bounded number of facts,
     * trends, predictions and advisories with their explicit classification
     * labels, plus the period and data-through timestamps. No credentials, no
     * member-level detail, no record identifiers and no raw instructions.
     *
     * @param  array<string, mixed>  $reportData
     */
    public function sanitizedContext(array $reportData): string
    {
        $limit = (int) config('intelligence-reporting.narrative.max_context_datums', 60);

        $lines = [
            'REPORT TYPE: '.($reportData['report_type_label'] ?? $reportData['report_type'] ?? 'Intelligence report'),
            'ORGANIZATION: '.data_get($reportData, 'scope.organization_id'),
            'BRANCH: '.(data_get($reportData, 'scope.branch_id') ?? 'All authorized branches'),
            'REPORTING PERIOD: '.data_get($reportData, 'period.start').' to '.data_get($reportData, 'period.end'),
            'DATA THROUGH: '.data_get($reportData, 'data_through'),
            '',
            'REPORT DATA',
        ];

        foreach ((array) ($reportData['sections'] ?? []) as $section) {
            $lines[] = '';
            $lines[] = 'SECTION: '.($section['title'] ?? $section['key'] ?? 'section');

            foreach (self::CLASSIFICATION_GROUPS as $classification => $heading) {
                // Sections store their groups under plural keys ("facts",
                // "trends", ...) so the classification vocabulary and the
                // serialized shape never drift apart.
                $rows = collect((array) ($section[$classification.'s'] ?? []))->take($limit);

                if ($rows->isEmpty()) {
                    continue;
                }

                $lines[] = '  '.$heading.':';

                foreach ($rows as $datum) {
                    $lines[] = '  - '.$this->datumLine($datum);
                }
            }

            foreach ((array) ($section['data_quality'] ?? []) as $note) {
                $lines[] = '  DATA QUALITY: '.$note;
            }
        }

        $lines[] = '';
        $lines[] = 'Explain this report for management. Do not add figures that are not listed above.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $datum
     */
    protected function datumLine(array $datum): string
    {
        $classification = (string) ($datum['classification_label'] ?? $datum['classification'] ?? '');
        $label = (string) ($datum['label'] ?? '');
        $value = $datum['value'] ?? null;

        $line = '['.$classification.'] '.$label;

        if ($value !== null && is_scalar($value)) {
            $line .= ': '.$value;
        }

        if (isset($datum['previous_value']) && $datum['previous_value'] !== null) {
            $line .= ' (previous period '.($datum['previous_value'] ?? '').', direction '.($datum['direction_label'] ?? 'n/a').')';
        }

        if (! empty($datum['note'])) {
            $line .= ' — '.$datum['note'];
        }

        return $line;
    }

    /**
     * Whether the report carries any narrative at all, so the UI can show the
     * "AI narrative unavailable" state honestly.
     *
     * @param  array<string, mixed>|null  $narrative
     */
    public function isPresent(?array $narrative): bool
    {
        return is_array($narrative) && ($narrative['available'] ?? false) === true;
    }
}
