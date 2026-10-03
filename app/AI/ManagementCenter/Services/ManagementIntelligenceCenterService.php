<?php

namespace App\AI\ManagementCenter\Services;

use App\AI\DTOs\AiContextData;
use App\AI\FinancialIntelligence\Services\AccountingIntelligenceService;
use App\AI\FinancialIntelligence\Services\CollectionIntelligenceService;
use App\AI\FinancialIntelligence\Services\DelinquencyIntelligenceService;
use App\AI\FinancialIntelligence\Services\FinancialTrendService;
use App\AI\FinancialIntelligence\Services\ParIntelligenceService;
use App\AI\FinancialIntelligence\Services\PortfolioIntelligenceService;
use App\AI\ManagementActions\Services\ManagementActionEffectivenessService;
use App\AI\ManagementActions\Services\ManagementActionQueryService;
use App\AI\Reporting\Data\ReportPeriod;
use App\AI\Reporting\Services\IntelligenceReportNarrativeService;
use App\AI\Reporting\Services\ReportPeriodService;
use App\Enums\PredictiveInsightType;
use App\Enums\ProactiveInsightSeverity;
use App\Enums\ProactiveInsightType;
use App\Enums\ReportDatumClassification;
use App\Enums\ReportPeriodType;
use App\Enums\ReportStatus;
use App\Models\AiAnomalyFinding;
use App\Models\AiInsight;
use App\Models\AiIntelligenceReport;
use App\Models\AiPrediction;
use App\Models\AiReportSchedule;
use App\Models\AiReportScheduleRun;
use App\Models\Organization;
use Illuminate\Support\Collection;

/**
 * Management Intelligence Center assembly (Phase 12.2).
 *
 * A pure, read-only aggregation layer. It never computes a financial figure of
 * its own: every descriptive section is delegated to the existing Phase 11.7
 * intelligence services over the trusted organization/branch scope, and every
 * advisory/predictive/reporting block is read straight from the persisted
 * Phase 11.8 / 11.9 / 12.0 / 12.1 artifacts. Loading the center never generates
 * a report, a prediction, an insight, a schedule run or a business record.
 *
 * The four-way classification contract (fact / trend / prediction / advisory)
 * is carried on every datum, so a consumer can never present an advisory or a
 * prediction as an observed fact.
 */
class ManagementIntelligenceCenterService
{
    public function __construct(
        private readonly PortfolioIntelligenceService $portfolio,
        private readonly ParIntelligenceService $par,
        private readonly DelinquencyIntelligenceService $delinquency,
        private readonly CollectionIntelligenceService $collections,
        private readonly FinancialTrendService $trends,
        private readonly AccountingIntelligenceService $accounting,
        private readonly ReportPeriodService $periods,
        private readonly IntelligenceReportNarrativeService $narrative,
        private readonly ManagementActionQueryService $actions,
        private readonly ManagementActionEffectivenessService $actionEffectiveness,
    ) {}

    /**
     * Assemble the whole center for the acting user's trusted context.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(AiContextData $context, array $filters = [], bool $withNarrative = false): array
    {
        $period = $this->resolvePeriod($filters);
        $organizationIds = $context->organizationIds;
        $branchId = isset($filters['branch_id']) && $filters['branch_id'] !== null && $filters['branch_id'] !== ''
            ? (int) $filters['branch_id']
            : null;
        $branchIds = $branchId !== null ? [$branchId] : $context->branchIds;

        $sections = [];
        $summary = [];

        $this->portfolioSection($context, $organizationIds, $branchIds, $sections, $summary);

        $this->riskSection($context, $organizationIds, $branchIds, $sections, $summary);

        $this->collectionsSection($context, $organizationIds, $branchIds, $period, $sections, $summary);

        $this->cashFlowSection($context, $organizationIds, $branchIds, $sections, $summary);

        $this->accountingSection($context, $organizationIds, $branchId, $sections, $summary);

        $this->operationsSection($context, $organizationIds, $branchIds, $sections, $summary);

        $predictions = $this->predictionsSection($context, $organizationIds, $filters);

        $attentionQueue = $this->attentionQueue($context, $organizationIds, $branchIds, $filters);

        $reports = $this->reports($context, $organizationIds, $branchIds, $filters);

        [$schedules, $recentRuns] = $this->schedules($context, $organizationIds, $branchIds);

        $counts = $this->counts($context, $organizationIds, $branchIds, $predictions, $attentionQueue, $reports, $schedules, $recentRuns);

        $this->appendAdvisorySummary($context, $summary, $counts);

        $overview = [
            'period' => $period,
            'period_types' => ReportPeriodType::cases(),
            'branch_id' => $branchId,
            'sections' => $sections,
            'executive_summary' => $summary,
            'predictions' => $predictions,
            'attention_queue' => $attentionQueue,
            'reports' => $reports,
            'schedules' => $schedules,
            'recent_runs' => $recentRuns,
            'management_follow_up' => $this->managementFollowUp($context),
            'counts' => $counts,
            'capabilities' => $this->capabilities($context),
            'narrative' => null,
        ];

        if ($withNarrative) {
            $overview['narrative'] = $this->buildNarrative($context, $period, $branchId, $sections, $counts);
        }

        return $overview;
    }

    /**
     * Resolve the requested period through the single Phase 12.0 period engine.
     *
     * @param  array<string, mixed>  $filters
     */
    public function resolvePeriod(array $filters): ReportPeriod
    {
        return $this->periods->resolve(
            (string) ($filters['period'] ?? ReportPeriodType::ThisMonth->value),
            isset($filters['from']) ? (string) $filters['from'] : null,
            isset($filters['to']) ? (string) $filters['to'] : null,
        );
    }

    /**
     * The capabilities that decide which sections are rendered. Kept explicit so
     * the controller/view never restate the mapping.
     *
     * @return array<string, bool>
     */
    public function capabilities(AiContextData $context): array
    {
        return [
            'portfolio' => $context->hasPermission('ai.portfolio.view'),
            'risk' => $context->hasPermission('ai.delinquency.view'),
            'collections' => $context->hasPermission('ai.collection.view'),
            'cash_flow' => $context->hasPermission('ai.trend.view'),
            'accounting' => $context->hasPermission('ai.accounting.view'),
            'operations' => $context->hasPermission('ai.anomaly.view') || $context->hasPermission('ai.insights.view'),
            'predictions' => $context->hasPermission('ai.predictive.view'),
            'insights' => $context->hasPermission('ai.insights.view'),
            'reports' => $context->hasPermission('ai.reports.view'),
            'schedules' => $context->hasPermission('ai.reports.schedule') && $context->hasPermission('ai.reports.view'),
            'actions' => $context->hasPermission('ai.actions.view'),
        ];
    }

    /**
     * The Executive Review "Management Follow-up" panel: human workflow counts,
     * the recently completed actions and — since Phase 12.4 — the action
     * effectiveness measurements.
     *
     * Read-only throughout: loading the center never creates, assigns, starts,
     * completes or cancels a management action, and never escalates one.
     *
     * @return array<string, mixed>
     */
    protected function managementFollowUp(AiContextData $context): array
    {
        if (! $context->hasPermission('ai.actions.view')) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'effectiveness' => $this->actionEffectiveness->centerPanel($context),
        ] + $this->actions->dashboard($context);
    }

    /**
     * @param  array<string, mixed>  $sections
     * @param  array<int, array<string, mixed>>  $summary
     * @param  array<int, int>  $organizationIds
     * @param  array<int, int>  $branchIds
     */
    protected function portfolioSection(AiContextData $context, array $organizationIds, array $branchIds, array &$sections, array &$summary): void
    {
        if (! $context->hasPermission('ai.portfolio.view')) {
            return;
        }

        $portfolio = $this->portfolio->summarize($organizationIds, $branchIds);

        $items = [
            $this->fact('Active loans', $portfolio->activeLoansCount, 'loans'),
            $this->fact('Outstanding principal', $portfolio->totalOutstanding, $portfolio->currency),
            $this->fact('Principal disbursed', $portfolio->totalPrincipalDisbursed, $portfolio->currency),
            $this->fact('Maturing within 30 days', $portfolio->maturingWithin30Days, $portfolio->currency),
            $this->fact('Maturing within 60 days', $portfolio->maturingWithin60Days, $portfolio->currency),
        ];

        $sections['portfolio'] = $this->section('portfolio', 'Portfolio', 'bi-diagram-3', $items, ['portfolio' => $portfolio]);
        $summary = array_merge($summary, $this->tag($items, 'Portfolio intelligence'));
    }

    /**
     * @param  array<string, mixed>  $sections
     * @param  array<int, array<string, mixed>>  $summary
     * @param  array<int, int>  $organizationIds
     * @param  array<int, int>  $branchIds
     */
    protected function riskSection(AiContextData $context, array $organizationIds, array $branchIds, array &$sections, array &$summary): void
    {
        if (! $context->hasPermission('ai.delinquency.view')) {
            return;
        }

        $par = $this->par->summarize($organizationIds, $branchIds);
        $delinquency = $this->delinquency->profile($organizationIds, $branchIds);

        $items = [
            $this->fact(sprintf('Portfolio at risk (%d+ days)', $par->parThresholdDays), $par->parRateOverThreshold, '%'),
            $this->fact('Outstanding principal', $par->totalPrincipalOutstanding, $par->currency),
            $this->fact('Delinquent principal', $delinquency->delinquentPrincipalOutstanding, $delinquency->currency),
            $this->fact('Delinquent loans', $delinquency->delinquentLoansCount, 'loans'),
        ];

        $sections['risk'] = $this->section('risk', 'Portfolio at risk & delinquency', 'bi-exclamation-triangle', $items, [
            'par' => $par,
            'delinquency' => $delinquency,
        ]);
        $summary = array_merge($summary, $this->tag($items, 'Delinquency intelligence'));
    }

    /**
     * @param  array<string, mixed>  $sections
     * @param  array<int, array<string, mixed>>  $summary
     * @param  array<int, int>  $organizationIds
     * @param  array<int, int>  $branchIds
     */
    protected function collectionsSection(AiContextData $context, array $organizationIds, array $branchIds, ReportPeriod $period, array &$sections, array &$summary): void
    {
        if (! $context->hasPermission('ai.collection.view')) {
            return;
        }

        $collections = $this->collections->summarize($organizationIds, $branchIds, $period->start, $period->end);

        $items = [
            $this->fact('Amount due', $collections->totalDue, $collections->currency),
            $this->fact('Collected', $collections->totalCollected, $collections->currency),
            $this->fact('Collection rate', $collections->collectionRate, '%'),
            $this->fact('Reversed amount', $collections->reversedAmount, $collections->currency),
        ];

        $sections['collections'] = $this->section('collections', 'Collections', 'bi-cash-coin', $items, ['collections' => $collections]);
        $summary = array_merge($summary, $this->tag($items, 'Collection intelligence'));
    }

    /**
     * Cash flow is presented from the authoritative monthly money-movement
     * series. The current-period movement is a trend; the window totals are
     * observed facts.
     *
     * @param  array<string, mixed>  $sections
     * @param  array<int, array<string, mixed>>  $summary
     * @param  array<int, int>  $organizationIds
     * @param  array<int, int>  $branchIds
     */
    protected function cashFlowSection(AiContextData $context, array $organizationIds, array $branchIds, array &$sections, array &$summary): void
    {
        if (! $context->hasPermission('ai.trend.view')) {
            return;
        }

        $trends = $this->trends->monthly($organizationIds, $branchIds, 12);
        $latest = $trends->trend === [] ? null : $trends->trend[array_key_last($trends->trend)];

        $items = [
            $this->trend(
                'Net cash movement ('.($latest['label'] ?? 'latest month').')',
                (float) ($latest['net_cash_flow'] ?? 0),
                $trends->currency,
                'Collected minus disbursed for the most recent observed month.',
            ),
            $this->fact('Disbursed (12-month window)', $trends->totalDisbursed, $trends->currency),
            $this->fact('Collected (12-month window)', $trends->totalCollected, $trends->currency),
        ];

        $sections['cash_flow'] = $this->section('cash_flow', 'Cash flow', 'bi-bar-chart', $items, ['trends' => $trends]);
        $summary = array_merge($summary, $this->tag($items, 'Money-movement trend'));
    }

    /**
     * Accounting intelligence is organization-scoped (the underlying report
     * services are), so a branch filter does not narrow it — the view states
     * that explicitly.
     *
     * @param  array<string, mixed>  $sections
     * @param  array<int, array<string, mixed>>  $summary
     * @param  array<int, int>  $organizationIds
     */
    protected function accountingSection(AiContextData $context, array $organizationIds, ?int $branchId, array &$sections, array &$summary): void
    {
        if (! $context->hasPermission('ai.accounting.view')) {
            return;
        }

        $accounting = $this->accounting->summarize($organizationIds);

        $items = [
            $this->fact('Total income', $accounting->totalIncome, $accounting->currency),
            $this->fact('Total expenses', $accounting->totalExpenses, $accounting->currency),
            $this->fact('Net income', $accounting->netIncome, $accounting->currency),
            $this->fact('Liquid position', $accounting->liquidPosition, $accounting->currency),
            $this->fact('Trial balance', $accounting->allBalanced ? 'Balanced' : 'Unbalanced', '', $accounting->allBalanced ? null : 'At least one organization has an unbalanced trial balance.'),
        ];

        $sections['accounting'] = $this->section('accounting', 'Accounting', 'bi-journal-text', $items, [
            'accounting' => $accounting,
            'branch_scoped' => $branchId !== null,
        ]);
        $summary = array_merge($summary, $this->tag($items, 'Accounting intelligence'));
    }

    /**
     * Operations: persisted anomaly findings from the latest detection pass plus
     * open operational proactive insights. No detection pass is run on view.
     *
     * @param  array<string, mixed>  $sections
     * @param  array<int, array<string, mixed>>  $summary
     * @param  array<int, int>  $organizationIds
     * @param  array<int, int>  $branchIds
     */
    protected function operationsSection(AiContextData $context, array $organizationIds, array $branchIds, array &$sections, array &$summary): void
    {
        if (! $context->hasPermission('ai.anomaly.view') && ! $context->hasPermission('ai.insights.view')) {
            return;
        }

        $items = [];
        $data = [];

        if ($context->hasPermission('ai.anomaly.view')) {
            $latestDate = AiAnomalyFinding::whereIn('organization_id', $organizationIds)
                ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->max('detection_date');

            $findings = $latestDate === null
                ? collect()
                : AiAnomalyFinding::whereIn('organization_id', $organizationIds)
                    ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
                    ->where('detection_date', $latestDate)
                    ->orderByDesc('id')
                    ->limit(25)
                    ->get();

            $data['findings'] = $findings;
            $data['findings_date'] = $latestDate;

            $items[] = $this->fact('Anomaly findings (latest pass)', $findings->count(), 'findings', $latestDate === null ? 'No anomaly detection pass has been recorded for this scope.' : null);
        }

        if ($context->hasPermission('ai.insights.view')) {
            $operational = AiInsight::whereIn('organization_id', $organizationIds)
                ->when($branchIds !== [], function ($query) use ($branchIds) {
                    $query->where(function ($inner) use ($branchIds) {
                        $inner->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                    });
                })
                ->where('type', ProactiveInsightType::OperationalGap->value)
                ->open()
                ->count();

            $data['operational_insights'] = $operational;
            $items[] = $this->advisory('Open operational insights', $operational, 'insights', 'Rule-based operational advisories awaiting a human decision.');
        }

        $sections['operations'] = $this->section('operations', 'Operations', 'bi-gear', $items, $data);
        $summary = array_merge($summary, $this->tag($items, 'Operational intelligence'));
    }

    /**
     * The latest persisted Phase 11.8 snapshot per domain and organization. No
     * snapshot is generated or refreshed here; whatever state was persisted
     * (including stale, poor quality or superseded) is shown as-is.
     *
     * @param  array<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    protected function predictionsSection(AiContextData $context, array $organizationIds, array $filters): array
    {
        if (! $context->hasPermission('ai.predictive.view')) {
            return [];
        }

        $types = PredictiveInsightType::cases();

        if (! empty($filters['prediction_type'])) {
            $types = array_values(array_filter(
                $types,
                fn (PredictiveInsightType $type) => $type->value === $filters['prediction_type'],
            ));
        }

        $snapshots = AiPrediction::forOrganizations($organizationIds)
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $organizationNames = Organization::whereIn('id', $organizationIds)->pluck('name', 'id');

        $domains = [];

        foreach ($types as $type) {
            $rows = [];

            foreach ($organizationIds as $organizationId) {
                $prediction = $snapshots->first(
                    fn (AiPrediction $snapshot) => (int) $snapshot->organization_id === (int) $organizationId
                        && $snapshot->type === $type,
                );

                $rows[] = [
                    'organization_id' => $organizationId,
                    'organization_name' => (string) ($organizationNames[$organizationId] ?? ('Organization #'.$organizationId)),
                    'prediction' => $prediction,
                    'scope_label' => $prediction === null
                        ? null
                        : $this->predictionScopeLabel($prediction),
                ];
            }

            $domains[] = [
                'type' => $type,
                'label' => $type->label(),
                'rows' => $rows,
            ];
        }

        return $domains;
    }

    /**
     * Attention Queue: persisted Phase 11.9 insights with the existing lifecycle
     * filters. Viewing the queue never marks an insight as read.
     *
     * @param  array<int, int>  $organizationIds
     * @param  array<int, int>  $branchIds
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AiInsight>
     */
    protected function attentionQueue(AiContextData $context, array $organizationIds, array $branchIds, array $filters): Collection
    {
        if (! $context->hasPermission('ai.insights.view')) {
            return collect();
        }

        $status = $filters['insight_status'] ?? null;

        return AiInsight::with('organization')
            ->forOrganizations($organizationIds)
            ->when($branchIds !== [], function ($query) use ($branchIds) {
                $query->where(function ($inner) use ($branchIds) {
                    $inner->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                });
            })
            ->when($status, fn ($query, $value) => $query->where('status', $value), fn ($query) => $query->open())
            ->when($filters['insight_severity'] ?? null, fn ($query, $value) => $query->where('severity', $value))
            ->when($filters['insight_type'] ?? null, fn ($query, $value) => $query->where('type', $value))
            ->orderByDesc('generated_at')
            ->limit(50)
            ->get()
            ->sortByDesc(fn (AiInsight $insight) => $insight->severity->priority())
            ->values();
    }

    /**
     * Report Library: persisted Phase 12.0 reports. Reading the library links to
     * the existing report detail page and never regenerates a report.
     *
     * @param  array<int, int>  $organizationIds
     * @param  array<int, int>  $branchIds
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AiIntelligenceReport>
     */
    protected function reports(AiContextData $context, array $organizationIds, array $branchIds, array $filters): Collection
    {
        if (! $context->hasPermission('ai.reports.view')) {
            return collect();
        }

        return AiIntelligenceReport::with(['organization', 'branch', 'requester'])
            ->forOrganizations($organizationIds)
            ->when($branchIds !== [], function ($query) use ($branchIds) {
                $query->where(function ($inner) use ($branchIds) {
                    $inner->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                });
            })
            ->when($filters['report_type'] ?? null, fn ($query, $value) => $query->where('report_type', $value))
            ->when($filters['report_status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['period'] ?? null, fn ($query, $value) => $query->where('period_type', $value))
            ->when($filters['generated_from'] ?? null, fn ($query, $value) => $query->whereDate('generated_at', '>=', $value))
            ->when($filters['generated_to'] ?? null, fn ($query, $value) => $query->whereDate('generated_at', '<=', $value))
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();
    }

    /**
     * Scheduled reports and their recent runs, read from the persisted Phase
     * 12.1 artifacts. No scheduler is invoked.
     *
     * @param  array<int, int>  $organizationIds
     * @param  array<int, int>  $branchIds
     * @return array{0: Collection<int, AiReportSchedule>, 1: Collection<int, AiReportScheduleRun>}
     */
    protected function schedules(AiContextData $context, array $organizationIds, array $branchIds): array
    {
        if (! $context->hasPermission('ai.reports.schedule') || ! $context->hasPermission('ai.reports.view')) {
            return [collect(), collect()];
        }

        $schedules = AiReportSchedule::with(['organization', 'branch', 'creator'])
            ->forOrganizations($organizationIds)
            ->when($branchIds !== [], function ($query) use ($branchIds) {
                $query->where(function ($inner) use ($branchIds) {
                    $inner->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                });
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $runs = $schedules->isEmpty()
            ? collect()
            : AiReportScheduleRun::with(['schedule', 'report'])
                ->whereIn('ai_report_schedule_id', $schedules->pluck('id'))
                ->orderByDesc('id')
                ->limit(25)
                ->get();

        return [$schedules, $runs];
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @param  array<int, int>  $branchIds
     * @param  array<int, array<string, mixed>>  $predictions
     * @param  Collection<int, AiInsight>  $attentionQueue
     * @param  Collection<int, AiIntelligenceReport>  $reports
     * @param  Collection<int, AiReportSchedule>  $schedules
     * @param  Collection<int, AiReportScheduleRun>  $runs
     * @return array<string, int>
     */
    protected function counts(
        AiContextData $context,
        array $organizationIds,
        array $branchIds,
        array $predictions,
        Collection $attentionQueue,
        Collection $reports,
        Collection $schedules,
        Collection $runs,
    ): array {
        $counts = [
            'open_insights' => 0,
            'critical_insights' => 0,
            'current_predictions' => 0,
            'reports_total' => 0,
            'reports_failed' => 0,
            'schedules_active' => 0,
            'runs_failed' => 0,
        ];

        if ($context->hasPermission('ai.insights.view')) {
            $openQuery = AiInsight::forOrganizations($organizationIds)
                ->when($branchIds !== [], function ($query) use ($branchIds) {
                    $query->where(function ($inner) use ($branchIds) {
                        $inner->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                    });
                })
                ->open();

            $counts['open_insights'] = (clone $openQuery)->count();
            $counts['critical_insights'] = (clone $openQuery)
                ->where('severity', ProactiveInsightSeverity::Critical->value)
                ->count();
        }

        if ($context->hasPermission('ai.predictive.view')) {
            $counts['current_predictions'] = collect($predictions)
                ->flatMap(fn (array $domain) => collect($domain['rows'])->pluck('prediction'))
                ->filter(fn ($prediction) => $prediction !== null && $prediction->status->isCurrent())
                ->count();
        }

        if ($context->hasPermission('ai.reports.view')) {
            $counts['reports_total'] = AiIntelligenceReport::forOrganizations($organizationIds)->count();
            $counts['reports_failed'] = AiIntelligenceReport::forOrganizations($organizationIds)
                ->where('status', ReportStatus::Failed->value)
                ->count();
        }

        if ($context->hasPermission('ai.reports.schedule') && $context->hasPermission('ai.reports.view')) {
            $counts['schedules_active'] = $schedules->where('is_active', true)->count();
            $counts['runs_failed'] = $runs->filter(fn (AiReportScheduleRun $run) => $run->status->value === 'failed')->count();
        }

        return $counts;
    }

    /**
     * Append the advisory/predictive counts to the executive summary so the
     * headline mixes facts, trends, predictions and advisories with explicit
     * labels.
     *
     * @param  array<int, array<string, mixed>>  $summary
     * @param  array<string, int>  $counts
     */
    protected function appendAdvisorySummary(AiContextData $context, array &$summary, array $counts): void
    {
        if ($context->hasPermission('ai.insights.view')) {
            $summary[] = $this->advisory('Open proactive insights', $counts['open_insights'], 'insights', 'Rule-based advisories awaiting a human decision.');
        }

        if ($context->hasPermission('ai.predictive.view')) {
            $summary[] = $this->prediction('Current predictive snapshots', $counts['current_predictions'], 'snapshots', 'Statistical indications, never guarantees, and never inputs to a business rule.');
        }

        if ($context->hasPermission('ai.reports.view')) {
            $summary[] = $this->fact('Persisted reports', $counts['reports_total'], 'reports');
        }
    }

    /**
     * Build the optional AI explanation over the sanitized classified payload.
     *
     * @param  array<string, mixed>  $sections
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    protected function buildNarrative(AiContextData $context, ReportPeriod $period, ?int $branchId, array $sections, array $counts): array
    {
        $narrativeSections = [];

        foreach ($sections as $section) {
            $groups = [
                'facts' => [],
                'trends' => [],
                'predictions' => [],
                'advisories' => [],
            ];

            foreach ($section['items'] as $item) {
                $classification = ReportDatumClassification::tryFrom((string) $item['classification']);

                if ($classification === null) {
                    continue;
                }

                $groups[$classification->groupKey()][] = [
                    'classification_label' => $classification->label(),
                    'label' => $item['label'],
                    'value' => $item['value'],
                    'unit' => $item['unit'],
                    'note' => $item['note'] ?? null,
                ];
            }

            $narrativeSections[] = [
                'key' => $section['key'],
                'title' => $section['title'],
                'facts' => $groups['facts'],
                'trends' => $groups['trends'],
                'predictions' => $groups['predictions'],
                'advisories' => $groups['advisories'],
                'data_quality' => [],
            ];
        }

        return $this->narrative->generate([
            'report_type' => 'management_intelligence_center',
            'report_type_label' => 'Management Intelligence Center',
            'scope' => [
                'organization_id' => count($context->organizationIds) === 1 ? (int) $context->organizationIds[0] : null,
                'organization_count' => count($context->organizationIds),
                'branch_id' => $branchId,
            ],
            'period' => $period->toArray(),
            'data_through' => now()->toISOString(),
            'sections' => $narrativeSections,
            'counts' => $counts,
        ]);
    }

    protected function predictionScopeLabel(AiPrediction $prediction): string
    {
        $branchIds = array_values(array_filter(array_map('intval', (array) ($prediction->assumptions['branch_ids'] ?? []))));

        if ($branchIds === []) {
            return 'Organization-wide';
        }

        return 'Branches: '.implode(', ', $branchIds);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{key: string, title: string, icon: string, items: array<int, array<string, mixed>>, data: array<string, mixed>}
     */
    protected function section(string $key, string $title, string $icon, array $items, array $data = []): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'icon' => $icon,
            'items' => $items,
            'data' => $data,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function tag(array $items, string $source): array
    {
        return array_map(fn (array $item) => $item + ['source' => $source], $items);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fact(string $label, mixed $value, string $unit, ?string $note = null): array
    {
        return $this->datum(ReportDatumClassification::Fact, $label, $value, $unit, $note);
    }

    /**
     * @return array<string, mixed>
     */
    protected function trend(string $label, mixed $value, string $unit, ?string $note = null): array
    {
        return $this->datum(ReportDatumClassification::Trend, $label, $value, $unit, $note);
    }

    /**
     * @return array<string, mixed>
     */
    protected function prediction(string $label, mixed $value, string $unit, ?string $note = null): array
    {
        return $this->datum(ReportDatumClassification::Prediction, $label, $value, $unit, $note);
    }

    /**
     * @return array<string, mixed>
     */
    protected function advisory(string $label, mixed $value, string $unit, ?string $note = null): array
    {
        return $this->datum(ReportDatumClassification::Advisory, $label, $value, $unit, $note);
    }

    /**
     * @return array<string, mixed>
     */
    protected function datum(ReportDatumClassification $classification, string $label, mixed $value, string $unit, ?string $note = null): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'unit' => $unit,
            'classification' => $classification->value,
            'classification_label' => $classification->label(),
            'classification_color' => $classification->color(),
            'note' => $note,
        ];
    }
}
