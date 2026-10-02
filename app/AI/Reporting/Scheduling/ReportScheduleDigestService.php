<?php

namespace App\AI\Reporting\Scheduling;

use App\Enums\ReportDatumClassification;
use App\Models\AiIntelligenceReport;
use App\Models\AiReportSchedule;

/**
 * Builds the executive digest that accompanies a scheduled report's
 * notification (Phase 12.1).
 *
 * The digest is a *presentation* of the report the Phase 12.0 reporting service
 * already computed — it introduces no new figure and computes nothing itself. It
 * is assembled purely from the report's own classified dataset, so the four
 * Phase 12.0 classifications are preserved verbatim and a fact can never be
 * re-labelled as a trend, a prediction or an advisory.
 *
 * When the schedule asks for the optional AI layer, the report's existing
 * Phase 12.0 narrative is excerpted as a short, clearly labelled piece of
 * advisory prose. It is bounded in length, and its absence never prevents the
 * report itself from being delivered.
 */
class ReportScheduleDigestService
{
    /**
     * The digest for one delivered report.
     *
     * @return array{title: string, summary: string, narrative: ?string, available: bool, facts: array<int, array<string, mixed>>, advisories: array<int, array<string, mixed>>}
     */
    public function build(AiReportSchedule $schedule, AiIntelligenceReport $report): array
    {
        $facts = $this->rows($report, ReportDatumClassification::Fact);
        $advisories = $this->rows($report, ReportDatumClassification::Advisory);

        $narrative = $this->narrativeExcerpt($report, $schedule);

        return [
            'title' => $this->title($schedule, $report),
            'summary' => $this->deterministicSummary($report, $facts, $advisories),
            'narrative' => $narrative,
            'available' => $facts !== [] || $advisories !== [],
            'facts' => $facts,
            'advisories' => $advisories,
        ];
    }

    /**
     * The notification headline, phrased from the schedule and the completed
     * period it reported on.
     */
    protected function title(AiReportSchedule $schedule, AiIntelligenceReport $report): string
    {
        return sprintf(
            '%s is ready (%s, %s to %s)',
            $report->report_type->label(),
            $schedule->frequency->label(),
            $report->period_start->toDateString(),
            $report->period_end->toDateString(),
        );
    }

    /**
     * A short deterministic summary built from the report's own facts, its open
     * advisories and its data-quality notes. No AI is involved, so the delivery
     * text can never be unavailable and can never contradict a figure.
     *
     * @param  array<int, array<string, mixed>>  $facts
     * @param  array<int, array<string, mixed>>  $advisories
     */
    protected function deterministicSummary(AiIntelligenceReport $report, array $facts, array $advisories): string
    {
        $scope = $report->branch?->name ?? 'All authorized branches';

        $parts = [sprintf(
            'Scope: %s. Data through %s.',
            $scope,
            $report->data_through?->toDateString() ?? 'unknown',
        )];

        foreach (array_slice($facts, 0, 3) as $datum) {
            $parts[] = sprintf('%s: %s', $datum['label'], $this->scalar($datum['value']));
        }

        if ($advisories !== []) {
            $parts[] = sprintf(
                '%d open advisory signal(s): %s',
                count($advisories),
                (string) $advisories[0]['label'],
            );
        }

        $notes = (array) data_get($report->report_data, 'data_quality', []);

        if ($notes !== []) {
            $parts[] = 'Data quality: '.(string) $notes[0];
        }

        return implode(' ', $parts);
    }

    /**
     * Rows of one classification, carrying the classification label and unit so
     * a value is never presented bare or re-labelled.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rows(AiIntelligenceReport $report, ReportDatumClassification $classification): array
    {
        $limit = (int) config('intelligence-reporting.scheduling.digest_max_headline', 6);

        if ($limit <= 0) {
            return [];
        }

        $rows = [];

        foreach ((array) data_get($report->report_data, 'sections', []) as $section) {
            foreach ((array) ($section[$classification->groupKey()] ?? []) as $datum) {
                if (count($rows) >= $limit) {
                    break 2;
                }

                $rows[] = [
                    'section' => $section['title'] ?? $section['key'] ?? null,
                    'classification' => $classification->value,
                    'classification_label' => $datum['classification_label'] ?? $classification->label(),
                    'label' => (string) ($datum['label'] ?? ''),
                    'value' => $datum['value'] ?? null,
                    'unit' => $datum['unit'] ?? null,
                    'direction_label' => $datum['direction_label'] ?? null,
                    'note' => $datum['note'] ?? null,
                ];
            }
        }

        return $rows;
    }

    /**
     * A bounded excerpt of the report's own Phase 12.0 narrative, clearly
     * labelled as AI-generated explanation. Returns null whenever there is no
     * narrative or it was unavailable — the delivery then relies on the
     * deterministic summary above.
     */
    protected function narrativeExcerpt(AiIntelligenceReport $report, AiReportSchedule $schedule): ?string
    {
        if (! $schedule->include_narrative) {
            return null;
        }

        $narrative = $report->narrative;

        if (! is_array($narrative) || ($narrative['available'] ?? false) !== true) {
            return null;
        }

        $summary = trim((string) ($narrative['summary'] ?? ''));

        if ($summary === '') {
            return null;
        }

        $limit = (int) config('intelligence-reporting.scheduling.digest_max_narrative_chars', 600);

        if ($limit <= 0) {
            return null;
        }

        $excerpt = mb_strlen($summary) > $limit
            ? mb_substr($summary, 0, $limit).'…'
            : $summary;

        return 'AI explanation (advisory prose, not a financial figure): '.$excerpt;
    }

    protected function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'not available';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES) ?: 'not available';
    }
}
