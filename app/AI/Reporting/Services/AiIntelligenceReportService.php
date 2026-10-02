<?php

namespace App\AI\Reporting\Services;

use App\AI\DTOs\AiContextData;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\AiIntelligenceReport;
use App\Models\Branch;
use App\Models\Organization;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use InvalidArgumentException;
use Throwable;

/**
 * The Phase 12.0 report aggregation service: the single entry point for
 * producing, persisting and listing intelligence reports.
 *
 * It owns request validation, tenant-scope resolution, report-type
 * authorization, period resolution, dataset assembly, the optional AI
 * narrative, persistence and auditing — so no controller has to orchestrate
 * report logic. A failed generation is recorded as `failed` with a safe
 * diagnostic and can never be read as a successful financial report.
 */
class AiIntelligenceReportService
{
    public function __construct(
        private readonly ReportPeriodService $periods,
        private readonly ReportBuilderService $builder,
        private readonly IntelligenceReportNarrativeService $narrative,
        private readonly AuditService $audit,
    ) {}

    /**
     * Generate a report for one organization within the acting user's trusted
     * scope.
     *
     * `$asOf` exists for the Phase 12.1 scheduler: it lets a scheduled run
     * resolve its completed period in the *schedule's* own timezone instead of
     * the application default, so a schedule's reporting day never shifts with
     * the server clock. It only moves the period engine's notion of "today";
     * every authorization decision still comes from `$context`.
     */
    public function generate(
        AiContextData $context,
        Authenticatable $user,
        string $reportType,
        string $periodType,
        ?string $from = null,
        ?string $to = null,
        ?string $branchId = null,
        bool $withNarrative = false,
        bool $persist = true,
        ?CarbonImmutable $asOf = null,
    ): AiIntelligenceReport {
        $type = ReportType::tryFrom($reportType);

        if ($type === null) {
            throw new InvalidArgumentException('Unsupported report type.');
        }

        // Tenant scope: the organization must be one of the acting user's own.
        $organizationId = $this->resolveOrganization($context);

        // An accounting report is authorized only for a holder of the existing
        // accounting capability. The reporting layer never widens access.
        if ($type->requiresAccountingCapability() && ! $context->hasPermission('ai.accounting.view')) {
            throw new InvalidArgumentException('Unauthorized report type.');
        }

        $branchId = $this->resolveBranch($context, $branchId);

        // A branch-scoped report may never claim a branch of another
        // organization, even when the organization itself is authorized.
        if ($branchId !== null) {
            $branch = Branch::find($branchId);

            if ($branch === null || (int) $branch->organization_id !== $organizationId) {
                throw new InvalidArgumentException('Unauthorized branch scope.');
            }
        }

        $shouldPersist = (bool) config('intelligence-reporting.persist', true) && $persist;

        $report = $shouldPersist
            ? $this->openReportRow($organizationId, $branchId, $type->value, $periodType, $user)
            : null;

        try {
            $period = $this->periods->resolve($periodType, $from, $to, $asOf);

            $reportData = $this->builder->build(
                type: $type,
                context: $context,
                organizationId: $organizationId,
                period: $period,
                branchId: $branchId,
            );

            $narrativePayload = $withNarrative
                ? $this->narrative->generate($reportData)
                : null;

            $reportData['period'] = $period->toArray();
            $reportData['narrative'] = $narrativePayload;
            $reportData['data_quality'] = $this->dataQualityNotes($reportData);

            if ($report !== null) {
                $report->update([
                    'status' => ReportStatus::Completed->value,
                    'period_type' => $period->type,
                    'period_start' => $period->start,
                    'period_end' => $period->end,
                    'previous_period_start' => $period->previousStart,
                    'previous_period_end' => $period->previousEnd,
                    'data_through' => $reportData['data_through'],
                    'generated_at' => $reportData['generated_at'],
                    'report_data' => $reportData,
                    'narrative' => $narrativePayload,
                    'ai_generated' => $narrativePayload !== null,
                    'failure_reason' => null,
                ]);

                $this->audit->log('ai.report.generated', $report, [], [
                    'report_type' => $type->value,
                    'period_type' => $period->type,
                    'period_start' => $period->start,
                    'period_end' => $period->end,
                    'branch_id' => $branchId,
                    'sections' => count((array) ($reportData['sections'] ?? [])),
                    'narrative_available' => ($narrativePayload['available'] ?? false) === true,
                ]);
            }

            return $report?->refresh() ?? $this->transientReport($organizationId, $branchId, $type->value, $period->type, $reportData, $narrativePayload, $user);
        } catch (InvalidArgumentException $exception) {
            $this->failReport($report, $exception->getMessage());

            throw $exception;
        } catch (Throwable $exception) {
            $this->failReport($report, 'The report could not be generated.');

            throw new InvalidArgumentException('The report could not be generated.', 0, $exception);
        }
    }

    /**
     * Recent reports for the acting user's scope.
     *
     * @return EloquentCollection<int, AiIntelligenceReport>
     */
    public function recent(AiContextData $context, int $limit = 25): EloquentCollection
    {
        return AiIntelligenceReport::with(['organization', 'branch', 'requester'])
            ->forOrganizations($context->organizationIds)
            ->when($context->branchIds !== [], function ($query) use ($context) {
                $query->where(function ($inner) use ($context) {
                    $inner->whereNull('branch_id')->orWhereIn('branch_id', $context->branchIds);
                });
            })
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->limit(min(max(1, $limit), (int) config('intelligence-reporting.recent_limit', 25)))
            ->get();
    }

    /**
     * Report types the acting user may generate: every type, except that the
     * accounting report additionally requires the accounting capability.
     *
     * @return array<int, ReportType>
     */
    public function availableTypes(AiContextData $context): array
    {
        return array_values(array_filter(
            ReportType::cases(),
            fn (ReportType $type) => ! $type->requiresAccountingCapability() || $context->hasPermission('ai.accounting.view'),
        ));
    }

    /**
     * A report the acting user is authorized to read. Ownership is enforced
     * server-side from the trusted context, so a foreign report id is a 404 /
     * 403 rather than a disclosure.
     */
    public function findAuthorized(AiContextData $context, int $reportId): AiIntelligenceReport
    {
        $report = AiIntelligenceReport::forOrganizations($context->organizationIds)
            ->orderByDesc('id')
            ->firstWhere('id', $reportId);

        if ($report === null) {
            throw new InvalidArgumentException('Report not found.');
        }

        if ($report->branch_id !== null && $context->branchIds !== []
            && ! $context->belongsToBranch((int) $report->branch_id)) {
            throw new InvalidArgumentException('Unauthorized branch scope.');
        }

        return $report;
    }

    /**
     * Flatten a report into CSV rows for the export, straight from the stored
     * structured dataset. The narrative is exported as a labelled narrative
     * block only; it never replaces or alters a figure.
     *
     * @return array{header: array<int, string>, rows: array<int, array<int, string>>, metadata: array<string, string>}
     */
    public function toCsv(AiIntelligenceReport $report): array
    {
        $header = ['Section', 'Classification', 'Label', 'Value', 'Unit', 'Previous value', 'Absolute change', 'Percentage change', 'Direction', 'Source', 'Note'];

        $rows = [];

        foreach ((array) data_get($report->report_data, 'sections', []) as $section) {
            foreach (['facts', 'trends', 'predictions', 'advisories'] as $group) {
                foreach ((array) ($section[$group] ?? []) as $datum) {
                    $rows[] = [
                        (string) ($section['title'] ?? $section['key'] ?? ''),
                        (string) ($datum['classification_label'] ?? ''),
                        (string) ($datum['label'] ?? ''),
                        $this->scalar($datum['value'] ?? null),
                        (string) ($datum['unit'] ?? ''),
                        $this->scalar($datum['previous_value'] ?? null),
                        $this->scalar($datum['absolute_change'] ?? null),
                        $this->scalar($datum['percentage_change'] ?? null),
                        (string) ($datum['direction_label'] ?? ''),
                        (string) ($datum['source'] ?? ''),
                        (string) ($datum['note'] ?? ''),
                    ];
                }
            }
        }

        return [
            'header' => $header,
            'rows' => $rows,
            'metadata' => [
                'organization' => (string) ($report->organization?->name ?? $report->organization_id),
                'branch' => (string) ($report->branch?->name ?? 'All branches'),
                'report_type' => $report->report_type->label(),
                'period' => $report->period_start->toDateString().' to '.$report->period_end->toDateString(),
                'data_through' => (string) $report->data_through,
                'generated_at' => (string) $report->generated_at,
                'generated_by' => (string) ($report->requester?->fullname ?? $report->requested_by ?? 'system'),
            ],
        ];
    }

    /**
     * The organization the report is generated for: the acting user's single
     * organization, or the first of their assignments. Never request-supplied.
     */
    protected function resolveOrganization(AiContextData $context): int
    {
        if ($context->organizationIds === []) {
            throw new InvalidArgumentException('No authorized organization for this report.');
        }

        return (int) $context->organizationIds[0];
    }

    /**
     * Branch narrowing is accepted only when it is inside the trusted scope.
     */
    protected function resolveBranch(AiContextData $context, ?string $branchId): ?string
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }

        $branch = Branch::find((int) $branchId);

        if ($branch === null
            || ! $context->belongsToOrganization((int) $branch->organization_id)
            || ! $context->belongsToBranch((int) $branch->id)) {
            throw new InvalidArgumentException('Unauthorized branch scope.');
        }

        return (string) $branch->id;
    }

    protected function openReportRow(
        int $organizationId,
        ?string $branchId,
        string $reportType,
        string $periodType,
        Authenticatable $user,
    ): AiIntelligenceReport {
        return AiIntelligenceReport::create([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'report_type' => $reportType,
            'status' => ReportStatus::Generating->value,
            'period_type' => $periodType,
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'data_through' => now(),
            'generated_at' => now(),
            'requested_by' => $user->getAuthIdentifier(),
            'report_data' => [],
        ]);
    }

    /**
     * When persistence is disabled, an equivalent in-memory model instance is
     * returned so every consumer sees one contract.
     *
     * @param  array<string, mixed>  $reportData
     * @param  array<string, mixed>|null  $narrative
     */
    protected function transientReport(
        int $organizationId,
        ?string $branchId,
        string $reportType,
        string $periodType,
        array $reportData,
        ?array $narrative,
        Authenticatable $user,
    ): AiIntelligenceReport {
        $report = new AiIntelligenceReport([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'report_type' => $reportType,
            'status' => ReportStatus::Completed->value,
            'period_type' => $periodType,
            'period_start' => data_get($reportData, 'period.start'),
            'period_end' => data_get($reportData, 'period.end'),
            'previous_period_start' => data_get($reportData, 'period.previous_start'),
            'previous_period_end' => data_get($reportData, 'period.previous_end'),
            'data_through' => data_get($reportData, 'data_through'),
            'generated_at' => data_get($reportData, 'generated_at'),
            'requested_by' => $user->getAuthIdentifier(),
            'report_data' => $reportData,
            'narrative' => $narrative,
            'ai_generated' => $narrative !== null,
        ]);

        $report->setRelation('organization', Organization::find($organizationId));
        $report->setRelation('branch', $branchId !== null ? Branch::find((int) $branchId) : null);

        return $report;
    }

    protected function failReport(?AiIntelligenceReport $report, string $reason): void
    {
        $report?->update([
            'status' => ReportStatus::Failed->value,
            'failure_reason' => $reason,
        ]);

        if ($report !== null) {
            $this->audit->log('ai.report.failed', $report, [], [
                'report_type' => $report->report_type->value,
                'organization_id' => $report->organization_id,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * Aggregate, explicit data-quality limitations for the whole report.
     *
     * @param  array<string, mixed>  $reportData
     * @return array<int, string>
     */
    protected function dataQualityNotes(array $reportData): array
    {
        $notes = [];

        $notes = array_merge($notes, (array) data_get($reportData, 'data_quality', []));

        $narrative = data_get($reportData, 'narrative');

        if (is_array($narrative) && ($narrative['available'] ?? false) === false) {
            $notes[] = 'AI narrative unavailable: '.($narrative['reason'] ?? 'no narrative was generated.');
        }

        if ((int) data_get($reportData, 'period.length_days', 0) > 0
            && data_get($reportData, 'period.previous_start') === null) {
            $notes[] = 'No previous comparable period data — the comparison could not be calculated.';
        }

        return array_values(array_unique($notes));
    }

    protected function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
    }
}
