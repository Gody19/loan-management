<?php

namespace App\AI\Reporting\Services;

use App\AI\DTOs\AiContextData;
use App\AI\PredictiveIntelligence\Services\PredictiveIntelligenceService;
use App\AI\Reporting\Data\ReportDatum;
use App\AI\Reporting\Data\ReportSection;
use App\Enums\PredictiveInsightType;
use App\Enums\ProactiveInsightSeverity;
use App\Enums\ReportDatumClassification;
use App\Models\AiInsight;
use App\Models\AiPrediction;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Carries the Phase 11.8 predictions and the Phase 11.9 proactive insights
 * into a Phase 12.0 report.
 *
 * Nothing is reinterpreted here: a prediction keeps its own status, confidence,
 * data quality and window, and an insight keeps its own severity, status,
 * source, period and advisory caveat. A stale, superseded or quality-gated
 * signal is surfaced with that label attached rather than dropped, so a report
 * can never imply a stronger position than Phase 11.8 / Phase 11.9 support.
 */
class ReportIntelligenceService
{
    public function __construct(
        private readonly PredictiveIntelligenceService $predictive,
    ) {}

    /**
     * A prediction labelled with everything Phase 11.8 recorded about it.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function describePrediction(AiPrediction $prediction, array $extra = []): array
    {
        return [
            'prediction_type' => $prediction->type->value,
            'label' => $prediction->type->label(),
            'organization_id' => $prediction->organization_id,
            'organization_name' => $this->organizationName($prediction->organization_id),
            'value' => $prediction->value_total === null ? null : (float) $prediction->value_total,
            'currency' => $prediction->currency,
            'status' => $prediction->status->value,
            'status_label' => $prediction->status->label(),
            'is_current' => $prediction->status->isCurrent(),
            'confidence' => $prediction->confidence?->value,
            'data_quality' => $prediction->data_quality?->value,
            'method' => $prediction->method,
            'model_version' => $prediction->model_version,
            'horizon' => $prediction->horizon,
            'target_period' => $prediction->target_period,
            'data_from' => $prediction->data_from?->toDateString(),
            'data_through' => $prediction->data_through?->toDateString(),
            'explanation' => $prediction->explanation,
            'series' => (array) ($prediction->series ?? []),
            'factors' => (array) ($prediction->factors ?? []),
            'quality_gates' => (array) ($prediction->assumptions['quality_gates'] ?? []),
            'caveat' => 'Statistical indication computed from historical FinancePro records. An indication, never a guarantee.',
        ] + $extra;
    }

    /**
     * The latest available signal for one prediction domain, read straight from
     * the Phase 11.8 service. This never refreshes, regenerates or mutates a
     * prediction: a report reads what Phase 11.8 already produced, so building a
     * report can never change a prediction or fabricate one that does not
     * exist.
     */
    public function latestPrediction(PredictiveInsightType $type, int $organizationId): ?AiPrediction
    {
        return $this->predictive->latest($type, $organizationId);
    }

    /**
     * Every prediction domain for an organization, as report data. A missing or
     * unusable signal is reported explicitly as a data-quality note rather than
     * omitted.
     *
     * @return array{predictions: array<int, ReportDatum>, notes: array<int, string>}
     */
    public function predictionsFor(int $organizationId): array
    {
        $predictions = [];
        $notes = [];

        foreach (PredictiveInsightType::cases() as $type) {
            $prediction = $this->latestPrediction($type, $organizationId);

            if ($prediction === null) {
                $notes[] = $type->label().': prediction unavailable — no snapshot has been generated.';

                continue;
            }

            if (! $prediction->status->isCurrent()) {
                $notes[] = $type->label().': latest snapshot is '.$prediction->status->label().'.';

                continue;
            }

            $predictions[] = ReportDatum::prediction(
                key: $type->value,
                label: $type->label(),
                source: 'PredictiveIntelligenceService',
                note: 'Statistical outlook; an indication, never a guarantee.',
                meta: $this->describePrediction($prediction),
            );
        }

        return ['predictions' => $predictions, 'notes' => $notes];
    }

    /**
     * The Phase 11.9 insights authorized for the trusted scope, as advisory
     * data. The advisory caveat is carried on every row and the lifecycle is
     * described as the human action it is.
     *
     * @param  Collection<int, AiInsight>  $insights
     * @return array<int, ReportDatum>
     */
    public function advisories(Collection $insights): array
    {
        return $insights
            ->sortByDesc(fn (AiInsight $insight) => $insight->severity->priority())
            ->take((int) config('intelligence-reporting.max_rows_per_section', 25))
            ->map(fn (AiInsight $insight) => ReportDatum::advisory(
                key: $insight->type->value.'-'.$insight->id,
                label: $insight->title,
                source: 'ProactiveInsightService',
                note: $insight->recommendation,
                meta: [
                    'insight_id' => $insight->id,
                    'organization_id' => $insight->organization_id,
                    'branch_id' => $insight->branch_id,
                    'type' => $insight->type->value,
                    'type_label' => $insight->type->label(),
                    'severity' => $insight->severity->value,
                    'severity_label' => $insight->severity->label(),
                    'status' => $insight->status->value,
                    'status_label' => $insight->status->label(),
                    'title' => $insight->title,
                    'summary' => $insight->summary,
                    'recommendation' => $insight->recommendation,
                    'source' => $insight->source_type?->value,
                    'period_start' => $insight->period_start?->toDateString(),
                    'period_end' => $insight->period_end?->toDateString(),
                    'data_through' => $insight->data_through?->toDateString(),
                    'generated_at' => $insight->generated_at?->toISOString(),
                    'lifecycle' => 'Acknowledging, resolving or dismissing an insight is a human dashboard action, never performed by the AI.',
                    'caveat' => 'Deterministic, rule-based advisory for a human decision.',
                ],
            ))
            ->values()
            ->all();
    }

    /**
     * Group advisory data by the Phase 11.9 severity so a section can show the
     * most material signal without implying an action is required.
     *
     * @param  array<int, ReportDatum>  $advisories
     * @return array<int, array<string, mixed>>
     */
    public function advisoryRows(AiContextData $context, Collection $insights): array
    {
        return $insights
            ->sortByDesc(fn (AiInsight $insight) => $insight->severity->priority())
            ->take((int) config('intelligence-reporting.max_rows_per_section', 25))
            ->map(fn (AiInsight $insight) => [
                'id' => $insight->id,
                'organization_id' => $insight->organization_id,
                'type' => $insight->type->value,
                'type_label' => $insight->type->label(),
                'severity' => $insight->severity->value,
                'severity_label' => $insight->severity->label(),
                'severity_color' => $insight->severity->color(),
                'status' => $insight->status->value,
                'status_label' => $insight->status->label(),
                'title' => $insight->title,
                'summary' => $insight->summary,
                'recommendation' => $insight->recommendation,
                'period_start' => $insight->period_start?->toDateString(),
                'period_end' => $insight->period_end?->toDateString(),
                'data_through' => $insight->data_through?->toDateString(),
                'generated_at' => $insight->generated_at?->toISOString(),
                'classification' => ReportDatumClassification::Advisory->value,
                'classification_label' => ReportDatumClassification::Advisory->label(),
                'highest_severity' => $insight->severity === ProactiveInsightSeverity::Critical,
            ])
            ->values()
            ->all();
    }

    /**
     * Whether any advisory exists for the scope, so a section can be honest
     * about an empty advisory surface instead of implying one was withheld.
     *
     * @param  Collection<int, AiInsight>  $insights
     */
    public function hasAdvisories(Collection $insights): bool
    {
        return $insights->isNotEmpty();
    }

    /**
     * Newest authoritative record the report could observe: the latest posted
     * repayment or disbursement at or before now. Never a future-dated record.
     */
    public function dataThrough(?int $organizationId = null, ?CarbonImmutable $asOf = null): string
    {
        $asOf = $asOf ?? CarbonImmutable::now();

        $repayment = LoanRepayment::query()
            ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId))
            ->where('payment_date', '<=', $asOf)
            ->max('payment_date');

        $disbursement = Loan::query()
            ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId))
            ->where('disbursement_date', '<=', $asOf)
            ->max('disbursement_date');

        $newest = collect([$repayment, $disbursement])
            ->filter()
            ->map(fn ($value) => CarbonImmutable::parse($value)->startOfDay())
            ->sortDesc()
            ->first();

        return ($newest ?? $asOf->startOfDay())->toDateTimeString();
    }

    protected function organizationName(int $organizationId): ?string
    {
        $organization = Organization::find($organizationId);

        return $organization?->name;
    }

    /**
     * Section wrapper for an advisory surface so every report type attaches its
     * insights the same way.
     *
     * @param  Collection<int, AiInsight>  $insights
     */
    public function advisorySection(ReportSection $section, Collection $insights): ReportSection
    {
        if (! $this->hasAdvisories($insights)) {
            return $section->withDataQuality('No proactive insights are currently open for this scope.');
        }

        return $section->withAdvisories($this->advisories($insights));
    }

    /**
     * Total and active members for the executive surface, read from the
     * authoritative member records.
     */
    public function memberCounts(array $organizationIds, array $branchIds = []): array
    {
        if ($organizationIds === []) {
            return ['total' => 0, 'active' => 0];
        }

        $base = Member::whereIn('organization_id', $organizationIds)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds));

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->active()->count(),
        ];
    }
}
