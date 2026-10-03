<?php

namespace App\AI\ManagementActions\Services;

use App\AI\DTOs\AiContextData;
use App\AI\ManagementActions\Data\ManagementActionComparison;
use App\AI\Reporting\Data\ReportPeriod;
use App\Enums\ManagementActionStatus;
use App\Enums\ReportDatumClassification;
use App\Models\Branch;
use App\Models\ManagementAction;
use App\Models\ManagementActionEvent;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Management Action Effectiveness & Executive Accountability (Phase 12.4).
 *
 * A measurement layer over the Phase 12.3 action workflow. It answers one
 * question — "are follow-up actions being handled, completed, delayed and
 * reviewed effectively?" — and nothing else.
 *
 * What this service deliberately is not:
 *
 *  - It never writes. `ManagementActionService` is not even injected, so a
 *    status can never be changed and an action can never be created, assigned,
 *    escalated or closed as a consequence of measurement.
 *  - It never generates intelligence. No prediction, anomaly, insight, report,
 *    schedule or notification is produced here; existing artifacts are only read.
 *  - It never ranks or scores people. The assignment and branch breakdowns are
 *    neutral factual counts ordered by name, with no score, rank or verdict.
 *
 * Scope is established *in the query* through the Phase 12.3 authorization
 * service, so tenant and branch isolation is applied by the database engine
 * rather than filtered in PHP afterwards. Metrics are aggregate SQL
 * (COUNT/SUM/AVG/GROUP BY) over bounded, indexed columns; the action and event
 * tables are never loaded into memory to be counted.
 *
 * Every derived figure is honest about missing data: a zero denominator yields
 * null (never NaN, INF or a division error), and a missing timestamp is
 * excluded from its average rather than treated as zero.
 *
 * Every projection is built from a clone of the incoming query. Two different
 * aggregates can therefore never be layered onto the same builder, which would
 * silently duplicate or corrupt column aliases.
 */
class ManagementActionEffectivenessService
{
    /**
     * The bounded list limits used for the descriptive breakdowns. A breakdown is
     * a transparency aid, not a data export, so the truncation is always
     * reported and a partial list can never be mistaken for a complete one.
     */
    public const MAX_BREAKDOWN_ROWS = 50;

    public const RECENT_COMPLETED_LIMIT = 5;

    /**
     * Aging buckets for unresolved actions, measured from `created_at`.
     *
     * The buckets are a fixed presentation contract rather than configuration:
     * they are part of the documented metric definition, so making them
     * configurable per deployment would let two installations report different
     * buckets for identical data.
     *
     * @var array<int, array{key: string, label: string, min_days: int, max_days: ?int}>
     */
    public const AGING_BUCKETS = [
        ['key' => '0_3', 'label' => '0-3 days', 'min_days' => 0, 'max_days' => 3],
        ['key' => '4_7', 'label' => '4-7 days', 'min_days' => 4, 'max_days' => 7],
        ['key' => '8_14', 'label' => '8-14 days', 'min_days' => 8, 'max_days' => 14],
        ['key' => '15_30', 'label' => '15-30 days', 'min_days' => 15, 'max_days' => 30],
        ['key' => '31_plus', 'label' => '31+ days', 'min_days' => 31, 'max_days' => null],
    ];

    /**
     * Presentation labels for the five supported action sources, keyed by the
     * stable alias stored against an action. `standalone` is the absence of a
     * source and is therefore always reported, even at zero.
     *
     * @var array<string, string>
     */
    public const SOURCE_LABELS = [
        'insight' => 'Proactive Insight',
        'prediction' => 'Prediction',
        'report' => 'Management Report',
        'anomaly' => 'Anomaly Finding',
        'standalone' => 'Standalone',
    ];

    public function __construct(
        private readonly ManagementActionAuthorizationService $authorization,
    ) {}

    /**
     * The compact measurement block added to the Intelligence Center's
     * Management Follow-up panel.
     *
     * It carries only the *effectiveness* figures. The live open/overdue counters
     * continue to come from the Phase 12.3 query service, so the existing center
     * contract is extended rather than duplicated.
     *
     * @return array<string, mixed>
     */
    public function centerPanel(AiContextData $context): array
    {
        if (! $this->authorization->canView($context)) {
            return ['available' => false];
        }

        $metrics = $this->snapshot($context);

        return [
            'available' => true,
            'total_actions' => $metrics['total'],
            'unresolved_actions' => $metrics['unresolved'],
            'completion_rate' => $metrics['completion_rate'],
            'cancellation_rate' => $metrics['cancellation_rate'],
            'average_completion_days' => $metrics['average_completion_days'],
            'average_time_to_start_days' => $metrics['average_time_to_start_days'],
            'recently_completed' => $this->recentlyCompleted($context),
        ];
    }

    /**
     * The full effectiveness payload for the executive accountability page.
     *
     * The period cohort is "actions created between the period start and end".
     * Every cohort figure — counts, rates, averages, aging and the breakdowns —
     * is measured over that single cohort, so the numbers always reconcile: the
     * status counts sum to the total and both rates share one denominator.
     *
     * The separately labelled `live` block is the current operational state of
     * the whole authorized scope, because an action raised last year and still
     * open is genuinely outstanding today even when the selected period does not
     * contain it.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(AiContextData $context, ReportPeriod $period, array $filters = []): array
    {
        $this->authorization->assertCanView($context);

        $currentQuery = $this->cohort($context, $filters, $period->start, $period->end);
        $previousQuery = $period->hasPrevious()
            ? $this->cohort($context, $filters, (string) $period->previousStart, (string) $period->previousEnd)
            : null;

        $current = $this->cohortMetrics($currentQuery);
        $previous = $previousQuery === null ? null : $this->cohortMetrics($previousQuery);

        return [
            'available' => true,
            'period' => $period->toArray(),
            'has_comparison' => $previous !== null,
            'cohort' => [
                'label' => 'Actions raised in '.$period->label,
                'metrics' => $current,
                'data' => $this->classifiedData($current),
            ],
            'previous' => $previous === null ? null : [
                'label' => 'Actions raised in the previous period',
                'metrics' => $previous,
            ],
            'comparison' => $this->comparison($current, $previous),
            'aging' => $this->aging($currentQuery),
            'by_assignee' => $this->byAssignee($context, $currentQuery),
            'by_branch' => $this->byBranch($context, $currentQuery),
            'by_source' => $this->bySource($currentQuery),
            'live' => [
                'label' => 'Current state of every action in your authorized scope (not limited to the selected period)',
                'metrics' => $this->snapshot($context, $filters),
                'recently_completed' => $this->recentlyCompleted($context, $filters),
            ],
        ];
    }

    /**
     * The factual lifecycle measurements for a single action detail page.
     *
     * `ManagementActionEvent` is the authoritative history, so each milestone is
     * taken from the first matching timeline entry and only falls back to the
     * action's own timestamp when no event row exists. Nothing is inferred and
     * no recommendation is produced.
     *
     * @param  EloquentCollection<int, ManagementActionEvent>  $events
     * @return array<string, mixed>
     */
    public function lifecycle(ManagementAction $action, EloquentCollection $events): array
    {
        $milestone = function (string $name) use ($events): ?CarbonInterface {
            $event = $events->first(fn (ManagementActionEvent $candidate) => $candidate->event === $name);

            return $event?->created_at;
        };

        $createdAt = $milestone('created') ?? $action->created_at;
        $startedAt = $milestone('started') ?? $action->started_at;
        $completedAt = $milestone('completed') ?? $action->completed_at;
        $cancelledAt = $milestone('cancelled') ?? $action->cancelled_at;

        $closedAt = $completedAt ?? $cancelledAt;
        $measuredTo = $closedAt ?? CarbonImmutable::now();

        $counts = [];

        foreach (['created', 'updated', 'assigned', 'started', 'completed', 'cancelled'] as $type) {
            $counts[$type] = $events->where('event', $type)->count();
        }

        return [
            'available' => $createdAt !== null,
            'unavailable_reason' => $createdAt === null ? 'This action has no recorded creation time.' : null,
            'created_at' => $createdAt?->toDateTimeString(),
            'started_at' => $startedAt?->toDateTimeString(),
            'completed_at' => $completedAt?->toDateTimeString(),
            'cancelled_at' => $cancelledAt?->toDateTimeString(),
            'time_to_start_days' => $this->daysBetween($createdAt, $startedAt),
            'time_to_complete_days' => $this->daysBetween($createdAt, $completedAt),
            'total_age_days' => $this->daysBetween($createdAt, $measuredTo),
            'total_age_measured_to' => $closedAt !== null ? 'closure' : 'now',
            'event_counts' => $counts,
            'event_total' => $events->count(),
        ];
    }

    /**
     * The scoped, filtered, period-bounded cohort query that every cohort metric
     * and breakdown is measured from.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function cohort(AiContextData $context, array $filters, string $start, string $end): Builder
    {
        $query = $this->authorization->applyScope(ManagementAction::query(), $context);

        $this->applyScopeFilters($query, $context, $filters);

        return $query
            ->where('created_at', '>=', CarbonImmutable::parse($start)->startOfDay()->toDateTimeString())
            ->where('created_at', '<=', CarbonImmutable::parse($end)->endOfDay()->toDateTimeString());
    }

    /**
     * The live scope query (not bounded by the period) used for the current
     * state block and the Intelligence Center panel.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function scoped(AiContextData $context, array $filters = []): Builder
    {
        $query = $this->authorization->applyScope(ManagementAction::query(), $context);

        $this->applyScopeFilters($query, $context, $filters);

        return $query;
    }

    /**
     * Branch, source and assignee filters narrow the cohort.
     *
     * A status filter is deliberately *not* accepted here: filtering a "completed
     * actions" count by status would make the metric self-referential. A status
     * filter is applied to the detailed record list instead, where it is
     * meaningful.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyScopeFilters(Builder $query, AiContextData $context, array $filters): void
    {
        if (! empty($filters['branch_id']) && is_numeric($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        $source = $filters['source_type'] ?? null;

        if (is_string($source) && $source !== '') {
            if ($source === 'standalone') {
                $query->whereNull('source_type');
            } elseif (isset(ManagementAction::SOURCE_TYPES[$source])) {
                $query->where('source_type', ManagementAction::SOURCE_TYPES[$source]);
            }
        }

        $assignee = $filters['assigned_to'] ?? null;

        if ($assignee === 'me') {
            $query->where('assigned_to', $context->userId);
        } elseif ($assignee === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assignee !== null && $assignee !== '' && is_numeric($assignee)) {
            $query->where('assigned_to', (int) $assignee);
        }
    }

    /**
     * Deterministic measurements for a query: one aggregate round trip for the
     * counts plus two small aggregates for the timing averages.
     *
     * @return array<string, mixed>
     */
    protected function cohortMetrics(Builder $query): array
    {
        $metrics = $this->counts($query);

        $metrics['average_completion_days'] = $this->averageCompletionDays($query);
        $metrics['average_time_to_start_days'] = $this->averageDays($query, 'started_at', 'created_at');

        return $metrics;
    }

    /**
     * The aggregate count projection shared by the cohort and live blocks.
     *
     * @return array<string, mixed>
     */
    protected function counts(Builder $query): array
    {
        $row = $this->aggregateCountQuery($query)->first();

        $open = (int) ($row->open_count ?? 0);
        $inProgress = (int) ($row->in_progress_count ?? 0);
        $completed = (int) ($row->completed_count ?? 0);
        $cancelled = (int) ($row->cancelled_count ?? 0);
        $total = (int) ($row->total_count ?? 0);

        return [
            'total' => $total,
            'open' => $open,
            'in_progress' => $inProgress,
            'unresolved' => $open + $inProgress,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'overdue' => (int) ($row->overdue_count ?? 0),
            'due_soon' => (int) ($row->due_soon_count ?? 0),
            'unassigned' => (int) ($row->unassigned_count ?? 0),
            'without_due_date' => (int) ($row->without_due_date_count ?? 0),
            'completion_rate' => $this->share($completed, $total),
            'cancellation_rate' => $this->share($cancelled, $total),
            'resolvable_share' => $this->share($completed + $cancelled, $total),
        ];
    }

    /**
     * The live current-state measurement, optionally narrowed by the same scope
     * filters as the cohort.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function snapshot(AiContextData $context, array $filters = []): array
    {
        return $this->cohortMetrics($this->scoped($context, $filters));
    }

    /**
     * The conditional aggregate projection.
     *
     * Every conditional sum uses standard `CASE WHEN` arithmetic so identical
     * SQL is valid on the MySQL production environment and the SQLite test
     * environment. "Unassigned" is always the non-terminal definition (an action
     * with no assignee that still needs work), never a closed one.
     */
    protected function aggregateCountQuery(Builder $query): Builder
    {
        $open = ManagementActionStatus::Open->value;
        $inProgress = ManagementActionStatus::InProgress->value;
        $completed = ManagementActionStatus::Completed->value;
        $cancelled = ManagementActionStatus::Cancelled->value;
        $today = CarbonImmutable::today()->toDateString();
        $soonLimit = CarbonImmutable::today()
            ->addDays(max(0, (int) config('intelligence-actions.due_soon_days', 3)))
            ->toDateString();

        $columns = [
            ['COUNT(*) as total_count', []],
            ['SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_count', [$open]],
            ['SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_count', [$inProgress]],
            ['SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count', [$completed]],
            ['SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_count', [$cancelled]],
            ['SUM(CASE WHEN assigned_to IS NULL AND status IN (?, ?) THEN 1 ELSE 0 END) as unassigned_count', [$open, $inProgress]],
            ['SUM(CASE WHEN due_date IS NULL THEN 1 ELSE 0 END) as without_due_date_count', []],
            [
                'SUM(CASE WHEN due_date IS NOT NULL AND due_date < ? AND status IN (?, ?) THEN 1 ELSE 0 END) as overdue_count',
                [$today, $open, $inProgress],
            ],
            [
                'SUM(CASE WHEN due_date IS NOT NULL AND due_date >= ? AND due_date <= ? AND status IN (?, ?) THEN 1 ELSE 0 END) as due_soon_count',
                [$today, $soonLimit, $open, $inProgress],
            ],
        ];

        $aggregate = clone $query;

        foreach ($columns as $column) {
            $aggregate->selectRaw($column[0], $column[1]);
        }

        return $aggregate;
    }

    /**
     * The mean completion time in days, measured across completed actions only.
     *
     * The status restriction is applied to a clone: the caller keeps the full
     * cohort builder for its aging and breakdown projections, so an average can
     * never narrow the cohort that every other metric is measured from.
     */
    protected function averageCompletionDays(Builder $query): ?float
    {
        return $this->averageDays(
            (clone $query)->where('status', ManagementActionStatus::Completed->value),
            'completed_at',
            'created_at',
        );
    }

    /**
     * The mean elapsed days between two action timestamps, measured in the
     * database.
     *
     * Rows with a missing timestamp are excluded by `AVG` itself (SQL `AVG`
     * ignores NULLs) and the guard clauses make that explicit. With no
     * qualifying row the result is null — never zero and never NaN.
     */
    protected function averageDays(Builder $query, string $endColumn, string $startColumn): ?float
    {
        $aggregate = (clone $query)
            ->whereNotNull($endColumn)
            ->whereNotNull($startColumn)
            ->selectRaw('AVG('.$this->elapsedSecondsSql($endColumn, $startColumn).') as elapsed_seconds');

        $seconds = $aggregate->first()?->elapsed_seconds;

        if ($seconds === null) {
            return null;
        }

        return round((float) $seconds / 86400, 1);
    }

    /**
     * Elapsed seconds between two columns, expressed in the dialect of the active
     * connection so one deterministic metric works on MySQL and SQLite.
     */
    protected function elapsedSecondsSql(string $endColumn, string $startColumn): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "(CAST(strftime('%s', {$endColumn}) AS INTEGER) - CAST(strftime('%s', {$startColumn}) AS INTEGER))";
        }

        return "TIMESTAMPDIFF(SECOND, {$startColumn}, {$endColumn})";
    }

    /**
     * A percentage share with an explicit zero-denominator contract: a share of
     * nothing is null — not zero, not NaN and not infinite.
     */
    protected function share(int $part, int $total): ?float
    {
        if ($total <= 0) {
            return null;
        }

        return round($part / $total * 100, 1);
    }

    protected function daysBetween(?CarbonInterface $start, ?CarbonInterface $end): ?float
    {
        if (! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface) {
            return null;
        }

        return round(abs($end->diffInSeconds($start)) / 86400, 1);
    }

    /**
     * The cohort figures expressed as classified reporting data: an observed
     * count is a fact, a period-over-period movement is a trend. Nothing here is
     * ever presented as a prediction or an advisory.
     *
     * @param  array<string, mixed>  $metrics
     * @return array<int, array<string, mixed>>
     */
    protected function classifiedData(array $metrics): array
    {
        $items = [
            $this->datum(ReportDatumClassification::Fact, 'Actions raised', $metrics['total'], 'actions'),
            $this->datum(ReportDatumClassification::Fact, 'Completed', $metrics['completed'], 'actions'),
            $this->datum(ReportDatumClassification::Fact, 'Cancelled', $metrics['cancelled'], 'actions'),
            $this->datum(ReportDatumClassification::Fact, 'Unresolved', $metrics['unresolved'], 'actions'),
            $this->datum(ReportDatumClassification::Fact, 'Overdue', $metrics['overdue'], 'actions'),
            $this->datum(ReportDatumClassification::Fact, 'Completion rate', $metrics['completion_rate'], '%'),
            $this->datum(ReportDatumClassification::Fact, 'Average completion time', $metrics['average_completion_days'], 'days'),
        ];

        return array_map(fn (array $item): array => $item + [
            'available' => $item['value'] !== null,
            'unavailable_label' => 'N/A',
        ], $items);
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

    /**
     * The period-over-period comparison.
     *
     * Each comparison states its own availability. When the previous period has
     * nothing comparable the change and direction are explicitly unavailable
     * rather than zero, and a previous count of zero still yields an absolute
     * change but no percentage change.
     *
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>|null  $previous
     * @return array<string, array<string, mixed>>
     */
    protected function comparison(?array $current, ?array $previous): array
    {
        $unavailable = 'The previous period is not available for this selection.';

        if ($current === null) {
            $unavailable = 'The selected period has no resolvable range.';
        }

        $build = function (callable $factory) use ($current, $previous, $unavailable): array {
            if ($current === null || $previous === null) {
                return ManagementActionComparison::unavailable($unavailable)->toArray();
            }

            return $factory($current, $previous)->toArray();
        };

        return [
            'total' => $build(fn (array $c, array $p) => ManagementActionComparison::forCount($c['total'], $p['total'])),
            'completed' => $build(fn (array $c, array $p) => ManagementActionComparison::forCount($c['completed'], $p['completed'])),
            'cancelled' => $build(fn (array $c, array $p) => ManagementActionComparison::forCount($c['cancelled'], $p['cancelled'])),
            'completion_rate' => $build(fn (array $c, array $p) => ManagementActionComparison::forRate($c['completion_rate'], $p['completion_rate'])),
            'cancellation_rate' => $build(fn (array $c, array $p) => ManagementActionComparison::forRate($c['cancellation_rate'], $p['cancellation_rate'])),
        ];
    }

    /**
     * Aging of the cohort's unresolved actions, measured from `created_at`.
     *
     * Every bucket is projected from absolute date boundaries, which keeps the
     * SQL portable and index-friendly, and the buckets are mutually exclusive and
     * exhaustive over any row with a real creation time.
     *
     * The bucket counts are therefore summed exactly: a row whose creation time
     * cannot be placed in a bucket at all (a null timestamp, or a future-dated
     * clock skew) is reported separately as `unclassified` instead of being
     * absorbed into the oldest bucket. An action can never be presented as 31+
     * days old when its age is simply unknown.
     *
     * @return array<string, mixed>
     */
    protected function aging(Builder $query): array
    {
        $today = CarbonImmutable::today();
        $boundaries = [
            $today->subDays(3)->startOfDay(),
            $today->subDays(7)->startOfDay(),
            $today->subDays(14)->startOfDay(),
            $today->subDays(30)->startOfDay(),
        ];
        $tomorrow = $today->addDay()->startOfDay();
        $bounded = array_slice(self::AGING_BUCKETS, 0, count(self::AGING_BUCKETS) - 1);

        $aggregate = (clone $query)->reorder()->open();
        $aggregate->selectRaw('COUNT(*) as unresolved_count');

        foreach ($bounded as $index => $bucket) {
            // A SQL alias may not start with a digit, so the public bucket keys
            // ("0_3", "4_7") are prefixed before they reach the query.
            $aggregate->selectRaw(
                'SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as '.$this->bucketAlias($bucket['key']),
                [$boundaries[$index], $index === 0 ? $tomorrow : $boundaries[$index - 1]],
            );
        }

        // The oldest bucket is a real predicate rather than a residual, so it can
        // only ever contain actions that genuinely predate the 30-day boundary.
        $oldest = self::AGING_BUCKETS[count(self::AGING_BUCKETS) - 1];
        $aggregate->selectRaw(
            'SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) as '.$this->bucketAlias($oldest['key']),
            [$boundaries[count($boundaries) - 1]],
        );

        $row = $aggregate->first();
        $unresolved = (int) ($row->unresolved_count ?? 0);

        $buckets = [];
        $classified = 0;

        foreach (self::AGING_BUCKETS as $bucket) {
            $count = (int) ($row->{$this->bucketAlias($bucket['key'])} ?? 0);
            $classified += $count;
            $buckets[] = $bucket + ['count' => $count];
        }

        $unclassified = max(0, $unresolved - $classified);

        return [
            'unresolved' => $unresolved,
            'classified' => $classified,
            'unclassified' => $unclassified,
            'buckets' => $buckets,
        ];
    }

    /**
     * A bucket key is a presentation identifier, not a SQL identifier. Prefixing
     * it keeps a key like "0_3" usable as an array key in PHP and views while
     * remaining a legal column alias.
     */
    protected function bucketAlias(string $key): string
    {
        return 'bucket_'.$key;
    }

    /**
     * Neutral per-assignee workload counts.
     *
     * Deliberately not a performance view: rows are ordered by name, carry no
     * score, rank or verdict, and exist only for workload transparency.
     *
     * @return array<string, mixed>
     */
    protected function byAssignee(AiContextData $context, Builder $query): array
    {
        $rows = $this->breakdownQuery($query, 'assigned_to')->get();
        $truncated = $rows->count() > self::MAX_BREAKDOWN_ROWS;
        $rows = $rows->take(self::MAX_BREAKDOWN_ROWS);

        $names = $this->userNames($context, $rows->pluck('assigned_to')->filter()->all());

        $items = $rows->map(function ($row) use ($names) {
            $assigneeId = $row->assigned_to === null ? null : (int) $row->assigned_to;

            return [
                'assignee_id' => $assigneeId,
                'label' => $assigneeId === null ? 'Unassigned' : ($names[$assigneeId] ?? 'User #'.$assigneeId),
                'known' => $assigneeId === null || isset($names[$assigneeId]),
            ] + $this->breakdownValues($row);
        })->values()->all();

        return $this->breakdownResult($items, $truncated);
    }

    /**
     * Neutral per-branch workload counts, with the organization-wide actions
     * (no branch) reported as their own row.
     *
     * @return array<string, mixed>
     */
    protected function byBranch(AiContextData $context, Builder $query): array
    {
        $rows = $this->breakdownQuery($query, 'branch_id')->get();
        $truncated = $rows->count() > self::MAX_BREAKDOWN_ROWS;
        $rows = $rows->take(self::MAX_BREAKDOWN_ROWS);

        $names = $this->branchNames($context, $rows->pluck('branch_id')->filter()->all());

        $items = $rows->map(function ($row) use ($names, $context) {
            $branchId = $row->branch_id === null ? null : (int) $row->branch_id;

            return [
                'branch_id' => $branchId,
                'label' => $branchId === null ? $this->organizationWideLabel($context) : ($names[$branchId] ?? 'Branch #'.$branchId),
                'known' => $branchId === null || isset($names[$branchId]),
            ] + $this->breakdownValues($row);
        })->values()->all();

        return $this->breakdownResult($items, $truncated);
    }

    protected function organizationWideLabel(AiContextData $context): string
    {
        if (count($context->organizationIds) !== 1) {
            return 'Organization-wide (no branch)';
        }

        $name = Organization::whereKey($context->organizationIds[0])->value('name');

        return $name === null ? 'Organization-wide (no branch)' : 'Organization-wide ('.$name.')';
    }

    /**
     * Where the cohort's actions came from.
     *
     * All five categories are always present, including the zero rows, so the
     * distribution reads as a complete picture. This is descriptive workflow
     * provenance only: no source is ever described as more important, better or
     * higher performing than another.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function bySource(Builder $query): array
    {
        $rows = (clone $query)
            ->reorder()
            ->groupBy('source_type')
            ->select('source_type')
            ->selectRaw('COUNT(*) as total_count')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->source_type] = (int) $row->total_count;
        }

        $categories = [];

        foreach (ManagementAction::SOURCE_TYPES as $alias => $class) {
            $categories[] = $this->sourceRow($alias, $counts[$class] ?? 0);
        }

        $categories[] = $this->sourceRow('standalone', $counts[''] ?? 0);

        return $categories;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sourceRow(string $alias, int $count): array
    {
        return [
            'source' => $alias,
            'label' => self::SOURCE_LABELS[$alias] ?? ucfirst($alias),
            'count' => $count,
        ];
    }

    /**
     * The shared per-group projection for the assignee and branch breakdowns.
     */
    protected function breakdownQuery(Builder $query, string $column): Builder
    {
        $open = ManagementActionStatus::Open->value;
        $inProgress = ManagementActionStatus::InProgress->value;
        $completed = ManagementActionStatus::Completed->value;
        $cancelled = ManagementActionStatus::Cancelled->value;
        $today = CarbonImmutable::today()->toDateString();

        return (clone $query)
            ->reorder()
            ->groupBy($column)
            ->select($column)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_count', [$open])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_count', [$inProgress])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count', [$completed])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_count', [$cancelled])
            ->selectRaw('SUM(CASE WHEN assigned_to IS NULL AND status IN (?, ?) THEN 1 ELSE 0 END) as unassigned_count', [$open, $inProgress])
            ->selectRaw(
                'SUM(CASE WHEN due_date IS NOT NULL AND due_date < ? AND status IN (?, ?) THEN 1 ELSE 0 END) as overdue_count',
                [$today, $open, $inProgress],
            )
            ->orderBy($column)
            ->limit(self::MAX_BREAKDOWN_ROWS + 1);
    }

    /**
     * @return array<string, int>
     */
    protected function breakdownValues(object $row): array
    {
        return [
            'total' => (int) ($row->total_count ?? 0),
            'open' => (int) ($row->open_count ?? 0),
            'in_progress' => (int) ($row->in_progress_count ?? 0),
            'completed' => (int) ($row->completed_count ?? 0),
            'cancelled' => (int) ($row->cancelled_count ?? 0),
            'unassigned' => (int) ($row->unassigned_count ?? 0),
            'overdue' => (int) ($row->overdue_count ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function breakdownResult(array $items, bool $truncated): array
    {
        return [
            'truncated' => $truncated,
            'limit' => self::MAX_BREAKDOWN_ROWS,
            'rows' => $items,
        ];
    }

    /**
     * User display names for a breakdown, constrained to the acting user's own
     * organizations so a name from outside the trusted scope is never disclosed.
     *
     * The columns are qualified because the `organizations` existence subquery joins
     * the pivot table, where a bare `id` would be ambiguous.
     *
     * @param  array<int, mixed>  $userIds
     * @return array<int, string>
     */
    protected function userNames(AiContextData $context, array $userIds): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($userIds))));

        if ($ids === []) {
            return [];
        }

        return User::whereIn('users.id', $ids)
            ->when($context->organizationIds !== [], fn (Builder $query) => $query->whereHas(
                'organizations',
                fn (Builder $inner) => $inner->whereIn('organizations.id', $context->organizationIds),
            ))
            ->get(['users.id', 'fullname', 'username'])
            ->mapWithKeys(fn (User $user) => [(int) $user->id => (string) ($user->fullname ?: $user->username)])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $branchIds
     * @return array<int|string, string>
     */
    protected function branchNames(AiContextData $context, array $branchIds): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($branchIds))));

        if ($ids === []) {
            return [];
        }

        return Branch::whereIn('id', $ids)
            ->whereIn('organization_id', $context->organizationIds)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The bounded list of recently completed actions, used by both the center
     * panel and the accountability page.
     *
     * @param  array<string, mixed>  $filters
     * @return EloquentCollection<int, ManagementAction>
     */
    protected function recentlyCompleted(AiContextData $context, array $filters = []): EloquentCollection
    {
        return $this->scoped($context, $filters)
            ->with(['assignee', 'branch', 'organization'])
            ->where('status', ManagementActionStatus::Completed->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_COMPLETED_LIMIT)
            ->get();
    }
}
