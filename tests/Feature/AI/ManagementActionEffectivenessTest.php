<?php

namespace Tests\Feature\AI;

use App\AI\ManagementActions\Data\ManagementActionComparison;
use App\AI\ManagementActions\Services\ManagementActionEffectivenessService;
use App\AI\ManagementCenter\Services\ManagementIntelligenceCenterService;
use App\AI\Reporting\Services\ReportPeriodService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\ManagementActionPriority;
use App\Enums\ManagementActionStatus;
use App\Models\AiInsight;
use App\Models\Branch;
use App\Models\ManagementAction;
use App\Models\ManagementActionEvent;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use ReflectionClass;
use ReflectionParameter;

/**
 * Management Action Effectiveness & Executive Accountability (Phase 12.4).
 *
 * These tests pin the layer's load-bearing guarantees rather than its styling:
 * that every figure is a deterministic aggregate of existing Phase 12.3 records,
 * that a missing denominator or timestamp yields null and never a manufactured
 * zero, that aging buckets reconcile exactly with the unresolved total, that a
 * period comparison never invents a percentage change, that tenant and branch
 * scope is applied in SQL and cannot be widened by a request, and — most
 * importantly — that measuring effectiveness never writes anything.
 */
class ManagementActionEffectivenessTest extends AiTestCase
{
    /**
     * Pinned "now" so aging, overdue and due-soon boundaries are deterministic.
     */
    private const NOW = '2026-06-15 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse(self::NOW));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function viewer(Organization $org, ?Branch $branch = null, string $role = 'Organization Administrator'): User
    {
        $user = $this->staff($org, $role);

        if ($branch !== null) {
            $user->branches()->attach($branch->id);
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAction(Organization $org, ?Branch $branch, User $creator, array $overrides = []): ManagementAction
    {
        return ManagementAction::create(array_merge([
            'organization_id' => $org->id,
            'branch_id' => $branch?->id,
            'created_by' => $creator->id,
            'assigned_to' => null,
            'title' => 'Action '.uniqid(),
            'description' => null,
            'priority' => ManagementActionPriority::Medium->value,
            'status' => ManagementActionStatus::Open->value,
            'due_date' => null,
        ], $overrides));
    }

    /**
     * Write lifecycle timestamps without disturbing the audit trail, so a cohort
     * can be placed in a previous period deterministically.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function stamp(ManagementAction $action, array $attributes): ManagementAction
    {
        $action->timestamps = false;
        $action->forceFill($attributes)->saveQuietly();
        $action->timestamps = true;

        return $action->fresh();
    }

    /**
     * Backdate an action's creation time.
     */
    private function backdate(ManagementAction $action, string $when): ManagementAction
    {
        return $this->stamp($action, ['created_at' => CarbonImmutable::parse($when)]);
    }

    private function reportFor(User $user, string $periodType = 'this_month', array $filters = []): array
    {
        return app(ManagementActionEffectivenessService::class)->report(
            app(AiContextBuilderService::class)->build($user),
            app(ReportPeriodService::class)->resolve($periodType),
            $filters,
        );
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    private function assertBucket(array $report, string $key, int $expected): void
    {
        $buckets = collect($report['aging']['buckets'])->keyBy('key');

        $this->assertSame($expected, $buckets->get($key)['count'] ?? null, "Aging bucket [{$key}] did not match.");
    }

    // ------------------------------------------------------------------
    // Access control
    // ------------------------------------------------------------------

    public function test_a_viewer_can_open_the_effectiveness_page(): void
    {
        $org = $this->makeOrganization();
        $user = $this->viewer($org, $this->branch($org));

        $this->actingAs($user)
            ->get(route('ai.actions.effectiveness'))
            ->assertOk()
            ->assertSee('Action Effectiveness');
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('ai.actions.effectiveness'))->assertRedirect(route('login'));
    }

    public function test_roles_without_action_capability_are_forbidden(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);

        $this->actingAs($this->viewer($org, $branch, 'Secretary'))
            ->get(route('ai.actions.effectiveness'))
            ->assertForbidden();

        $this->actingAs($this->vicobaUser($org, $this->member($org)))
            ->get(route('ai.actions.effectiveness'))
            ->assertForbidden();
    }

    public function test_an_auditor_may_read_the_effectiveness_page(): void
    {
        $org = $this->makeOrganization();
        $auditor = $this->viewer($org, $this->branch($org), 'Auditor');

        $this->actingAs($auditor)->get(route('ai.actions.effectiveness'))->assertOk();
    }

    // ------------------------------------------------------------------
    // Counts and rates
    // ------------------------------------------------------------------

    public function test_cohort_counts_and_rates_share_one_denominator(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $this->makeAction($org, $branch, $user, ['status' => ManagementActionStatus::Completed->value]);
        $this->makeAction($org, $branch, $user, ['status' => ManagementActionStatus::Completed->value]);
        $this->makeAction($org, $branch, $user, ['status' => ManagementActionStatus::Cancelled->value]);
        $this->makeAction($org, $branch, $user, ['status' => ManagementActionStatus::InProgress->value]);

        $metrics = $this->reportFor($user)['cohort']['metrics'];

        $this->assertSame(4, $metrics['total']);
        $this->assertSame(2, $metrics['completed']);
        $this->assertSame(1, $metrics['cancelled']);
        $this->assertSame(1, $metrics['in_progress']);
        $this->assertSame(1, $metrics['unresolved']);

        // 2/4 and 1/4 of the very same four-action cohort.
        $this->assertSame(50.0, $metrics['completion_rate']);
        $this->assertSame(25.0, $metrics['cancellation_rate']);

        // The status counts must always reconcile with the cohort total.
        $this->assertSame(
            $metrics['total'],
            $metrics['open'] + $metrics['in_progress'] + $metrics['completed'] + $metrics['cancelled'],
        );
    }

    public function test_rates_and_averages_are_null_when_the_period_has_no_actions(): void
    {
        $org = $this->makeOrganization();
        $user = $this->viewer($org, $this->branch($org));

        $metrics = $this->reportFor($user)['cohort']['metrics'];

        // A share of nothing is unknown, not zero.
        $this->assertNull($metrics['completion_rate']);
        $this->assertNull($metrics['cancellation_rate']);
        $this->assertNull($metrics['average_completion_days']);
        $this->assertNull($metrics['average_time_to_start_days']);

        $this->assertSame(0, $metrics['total']);
        $this->assertSame(0, $metrics['unresolved']);
    }

    public function test_actions_from_other_periods_are_excluded_from_the_cohort_but_not_from_live_state(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $this->makeAction($org, $branch, $user);
        $old = $this->makeAction($org, $branch, $user);
        $this->backdate($old, '2025-01-10 10:00:00');

        $report = $this->reportFor($user);

        $this->assertSame(1, $report['cohort']['metrics']['total'], 'Only the June action belongs to the June cohort.');
        $this->assertSame(2, $report['live']['metrics']['total'], 'Both actions are part of the live authorized scope.');
    }

    public function test_overdue_due_soon_and_unassigned_use_the_documented_definitions(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);
        $today = CarbonImmutable::parse(self::NOW);

        config(['intelligence-actions.due_soon_days' => 3]);

        // Overdue: past due date and still unresolved.
        $this->makeAction($org, $branch, $user, ['due_date' => $today->subDay()->toDateString()]);
        // Due soon: inside the configured window and still unresolved.
        $this->makeAction($org, $branch, $user, ['due_date' => $today->addDays(2)->toDateString()]);
        // Past due but already completed: never overdue.
        $this->makeAction($org, $branch, $user, [
            'due_date' => $today->subDays(9)->toDateString(),
            'status' => ManagementActionStatus::Completed->value,
        ]);
        // Unassigned but still open: an outstanding ownership gap.
        $this->makeAction($org, $branch, $user, ['due_date' => null]);

        $metrics = $this->reportFor($user)['cohort']['metrics'];

        $this->assertSame(1, $metrics['overdue']);
        $this->assertSame(1, $metrics['due_soon']);
        $this->assertSame(3, $metrics['unassigned'], 'Unassigned counts the three still-open actions.');
        $this->assertSame(1, $metrics['without_due_date']);
    }

    public function test_an_unassigned_terminal_action_is_not_counted_as_unassigned(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $this->makeAction($org, $branch, $user, ['status' => ManagementActionStatus::Completed->value]);
        $this->makeAction($org, $branch, $user, ['status' => ManagementActionStatus::Cancelled->value]);

        $this->assertSame(0, $this->reportFor($user)['cohort']['metrics']['unassigned']);
    }

    // ------------------------------------------------------------------
    // Timing measurements
    // ------------------------------------------------------------------

    public function test_average_completion_time_uses_completed_actions_only(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        // Completed in 2 elapsed days.
        $first = $this->makeAction($org, $branch, $user, ['status' => ManagementActionStatus::Completed->value]);
        $this->backdate($first, '2026-06-01 09:00:00');
        $this->stamp($first, ['completed_at' => CarbonImmutable::parse('2026-06-03 09:00:00')]);

        // Completed in 6 elapsed days.
        $second = $this->makeAction($org, $branch, $user, ['status' => ManagementActionStatus::Completed->value]);
        $this->backdate($second, '2026-06-05 09:00:00');
        $this->stamp($second, ['completed_at' => CarbonImmutable::parse('2026-06-11 09:00:00')]);

        // A far slower open action must not dilute the completed-only average.
        $this->backdate($this->makeAction($org, $branch, $user), '2026-06-01 09:00:00');

        $metrics = $this->reportFor($user)['cohort']['metrics'];

        $this->assertSame(3, $metrics['total']);
        $this->assertSame(2, $metrics['completed']);
        $this->assertSame(4.0, $metrics['average_completion_days'], 'Mean of 2 and 6 elapsed days.');
    }

    public function test_average_time_to_start_uses_started_actions_and_ignores_the_rest(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $started = $this->makeAction($org, $branch, $user);
        $this->backdate($started, '2026-06-10 09:00:00');
        $this->stamp($started, ['started_at' => CarbonImmutable::parse('2026-06-11 09:00:00')]);

        // Never started: excluded rather than counted as zero elapsed time.
        $this->makeAction($org, $branch, $user);

        $metrics = $this->reportFor($user)['cohort']['metrics'];

        $this->assertSame(1.0, $metrics['average_time_to_start_days']);
    }

    // ------------------------------------------------------------------
    // Aging
    // ------------------------------------------------------------------

    public function test_aging_buckets_classify_unresolved_actions_by_age(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);
        $today = CarbonImmutable::parse(self::NOW);

        // Unresolved actions, one per bucket.
        $this->backdate($this->makeAction($org, $branch, $user), $today->subDays(1)->toDateTimeString());
        $this->backdate($this->makeAction($org, $branch, $user), $today->subDays(5)->toDateTimeString());
        $this->backdate($this->makeAction($org, $branch, $user), $today->subDays(10)->toDateTimeString());
        $this->backdate($this->makeAction($org, $branch, $user), $today->subDays(20)->toDateTimeString());
        $this->backdate($this->makeAction($org, $branch, $user), $today->subDays(45)->toDateTimeString());

        // Terminal actions are never aged, however old they are.
        $terminal = $this->makeAction($org, $branch, $user, ['status' => ManagementActionStatus::Completed->value]);
        $this->backdate($terminal, '2026-01-05 09:00:00');

        // A year-long cohort keeps every backdated action inside the period.
        $report = $this->reportFor($user, 'this_year');

        $this->assertSame(5, $report['aging']['unresolved']);
        $this->assertBucket($report, '0_3', 1);
        $this->assertBucket($report, '4_7', 1);
        $this->assertBucket($report, '8_14', 1);
        $this->assertBucket($report, '15_30', 1);
        $this->assertBucket($report, '31_plus', 1);
    }

    public function test_aging_buckets_reconcile_exactly_with_the_unresolved_total(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        for ($day = 0; $day <= 40; $day++) {
            $this->backdate(
                $this->makeAction($org, $branch, $user),
                CarbonImmutable::parse(self::NOW)->subDays($day)->toDateTimeString(),
            );
        }

        $aging = $this->reportFor($user, 'this_year')['aging'];

        $sum = collect($aging['buckets'])->sum('count');

        $this->assertSame(41, $aging['unresolved']);
        $this->assertSame(0, $aging['unclassified']);
        $this->assertSame($aging['unresolved'], $sum, 'Every unresolved action must land in exactly one bucket.');
    }

    public function test_aging_reports_no_unresolved_actions_cleanly(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $this->makeAction($org, $branch, $user, ['status' => ManagementActionStatus::Completed->value]);

        $aging = $this->reportFor($user)['aging'];

        $this->assertSame(0, $aging['unresolved']);
        $this->assertSame(0, collect($aging['buckets'])->sum('count'));
    }

    // ------------------------------------------------------------------
    // Distribution breakdowns
    // ------------------------------------------------------------------

    public function test_assignment_distribution_separates_unassigned_work(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $this->makeAction($org, $branch, $user, ['assigned_to' => $user->id]);
        $this->makeAction($org, $branch, $user);
        $this->makeAction($org, $branch, $user);

        $rows = collect($this->reportFor($user)['by_assignee']['rows'])->keyBy('label');

        $this->assertSame(2, $rows['Unassigned']['total']);
        $this->assertSame(2, $rows['Unassigned']['unassigned']);
        $this->assertSame(1, $rows[$user->fullname]['total']);
    }

    public function test_assignment_rows_carry_no_score_or_rank(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $this->makeAction($org, $branch, $user, ['assigned_to' => $user->id]);

        foreach ($this->reportFor($user)['by_assignee']['rows'] as $row) {
            $this->assertArrayNotHasKey('score', $row);
            $this->assertArrayNotHasKey('rank', $row);
            $this->assertArrayNotHasKey('performance', $row);
        }
    }

    public function test_branch_distribution_includes_the_organization_wide_row(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org); // organization-wide reach

        $this->makeAction($org, $branch, $user);
        $this->makeAction($org, null, $user);

        $rows = collect($this->reportFor($user)['by_branch']['rows']);

        $this->assertCount(2, $rows);
        $this->assertTrue($rows->contains(fn ($row) => str_starts_with($row['label'], 'Organization-wide')));
        $this->assertTrue($rows->contains(fn ($row) => $row['label'] === $branch->name));
    }

    public function test_a_branch_limited_user_only_measures_their_own_branch(): void
    {
        $org = $this->makeOrganization();
        $mine = $this->branch($org);
        $other = $this->branch($org);
        $user = $this->viewer($org, $mine);

        $this->makeAction($org, $mine, $user);
        $this->makeAction($org, $other, $user);
        $this->makeAction($org, null, $user); // organization-wide: out of reach

        $report = $this->reportFor($user);

        $this->assertSame(1, $report['cohort']['metrics']['total']);
        $this->assertCount(1, $report['by_branch']['rows']);
    }

    public function test_source_distribution_reports_every_category_including_standalone(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $this->makeAction($org, $branch, $user, [
            'source_type' => AiInsight::class,
        ]);
        $this->makeAction($org, $branch, $user); // no source

        $sources = collect($this->reportFor($user)['by_source'])->keyBy('source');

        $this->assertSame(['insight', 'prediction', 'report', 'anomaly', 'standalone'], $sources->keys()->all());
        $this->assertSame(1, $sources['insight']['count']);
        $this->assertSame(1, $sources['standalone']['count']);
        $this->assertSame(0, $sources['anomaly']['count']);
    }

    public function test_the_standalone_source_filter_agrees_with_the_metric(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $this->makeAction($org, $branch, $user, ['source_type' => AiInsight::class]);
        $this->makeAction($org, $branch, $user);
        $this->makeAction($org, $branch, $user);

        $report = $this->reportFor($user, 'this_month', ['source_type' => 'standalone']);

        $this->assertSame(2, $report['cohort']['metrics']['total']);

        $this->actingAs($user)
            ->get(route('ai.actions.effectiveness', ['source_type' => 'standalone']))
            ->assertOk()
            ->assertViewHas('actions', fn ($actions) => $actions->total() === 2);
    }

    // ------------------------------------------------------------------
    // Period comparison
    // ------------------------------------------------------------------

    public function test_period_comparison_reports_change_and_direction(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        // Previous month: two actions, one completed.
        $this->backdate($this->makeAction($org, $branch, $user), '2026-05-05 09:00:00');
        $previousCompleted = $this->makeAction($org, $branch, $user);
        $this->backdate($previousCompleted, '2026-05-06 09:00:00');
        $previousCompleted->update(['status' => ManagementActionStatus::Completed->value]);

        // This month: four actions, two completed.
        for ($index = 0; $index < 4; $index++) {
            $action = $this->makeAction($org, $branch, $user, [
                'status' => $index < 2 ? ManagementActionStatus::Completed->value : ManagementActionStatus::Open->value,
            ]);
            $this->backdate($action, '2026-06-0'.($index + 1).' 09:00:00');
        }

        $comparison = $this->reportFor($user)['comparison'];

        $this->assertTrue($comparison['total']['available']);
        $this->assertSame(4, $comparison['total']['current']);
        $this->assertSame(2, $comparison['total']['previous']);
        $this->assertSame(2.0, $comparison['total']['change']);
        $this->assertSame(100.0, $comparison['total']['percent_change']);
        $this->assertSame(ManagementActionComparison::DIRECTION_UP, $comparison['total']['direction']);

        // A rate change is expressed in percentage points.
        $this->assertSame(50.0, $comparison['completion_rate']['current']);
        $this->assertSame(50.0, $comparison['completion_rate']['previous']);
        $this->assertSame(0.0, $comparison['completion_rate']['change']);
        $this->assertSame(ManagementActionComparison::DIRECTION_FLAT, $comparison['completion_rate']['direction']);
    }

    public function test_a_comparison_against_an_empty_previous_period_reports_no_percentage(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $this->makeAction($org, $branch, $user);
        $this->makeAction($org, $branch, $user);

        $comparison = $this->reportFor($user)['comparison'];

        $this->assertTrue($comparison['total']['available']);
        $this->assertSame(0, $comparison['total']['previous']);
        $this->assertSame(2.0, $comparison['total']['change']);
        $this->assertNull($comparison['total']['percent_change'], 'Growth from zero has no honest percentage.');
        $this->assertSame(ManagementActionComparison::DIRECTION_UP, $comparison['total']['direction']);

        // No action in the previous period means no comparable rate at all.
        $this->assertFalse($comparison['completion_rate']['available']);
        $this->assertSame(ManagementActionComparison::DIRECTION_UNAVAILABLE, $comparison['completion_rate']['direction']);
    }

    public function test_comparison_decreases_are_reported_as_a_direction_not_a_verdict(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        for ($index = 0; $index < 5; $index++) {
            $this->backdate($this->makeAction($org, $branch, $user), '2026-05-1'.$index.' 09:00:00');
        }

        $this->makeAction($org, $branch, $user);

        $comparison = $this->reportFor($user)['comparison'];

        $this->assertSame(1, $comparison['total']['current']);
        $this->assertSame(5, $comparison['total']['previous']);
        $this->assertSame(-4.0, $comparison['total']['change']);
        $this->assertSame(-80.0, $comparison['total']['percent_change']);
        $this->assertSame(ManagementActionComparison::DIRECTION_DOWN, $comparison['total']['direction']);
    }

    public function test_a_custom_period_is_measured_over_exactly_its_own_window(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $period = app(ReportPeriodService::class)->resolve('custom', '2026-03-01', '2026-03-31');

        $inMarch = $this->makeAction($org, $branch, $user);
        $this->backdate($inMarch, '2026-03-15 09:00:00');

        $this->makeAction($org, $branch, $user);
        $this->backdate($this->makeAction($org, $branch, $user), '2026-02-10 09:00:00');

        $report = app(ManagementActionEffectivenessService::class)->report(
            app(AiContextBuilderService::class)->build($user),
            $period,
        );

        $this->assertSame(1, $report['cohort']['metrics']['total']);
    }

    // ------------------------------------------------------------------
    // Read-only guarantee
    // ------------------------------------------------------------------

    public function test_measuring_effectiveness_never_writes_anything(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $action = $this->makeAction($org, $branch, $user);
        $this->backdate($action, '2026-05-05 09:00:00');

        // A plain scalar snapshot, so the comparison is by value and not by the
        // identity of two freshly hydrated model instances.
        $snapshot = fn (): array => [
            'status' => ManagementAction::firstOrFail()->status->value,
            'assigned_to' => ManagementAction::firstOrFail()->assigned_to,
            'due_date' => ManagementAction::firstOrFail()->due_date?->toDateString(),
            'started_at' => ManagementAction::firstOrFail()->started_at?->toDateTimeString(),
            'completed_at' => ManagementAction::firstOrFail()->completed_at?->toDateTimeString(),
            'cancelled_at' => ManagementAction::firstOrFail()->cancelled_at?->toDateTimeString(),
            'updated_at' => ManagementAction::firstOrFail()->updated_at?->toDateTimeString(),
        ];

        $before = $snapshot();
        $actionCount = ManagementAction::count();
        $eventCount = ManagementActionEvent::count();

        $this->actingAs($user)->get(route('ai.actions.effectiveness'))->assertOk();
        $this->actingAs($user)->get(route('ai.actions.effectiveness', ['period' => 'previous_month']))->assertOk();
        $this->actingAs($user)->get(route('ai.intelligence-center.index'))->assertOk();

        $this->assertSame($actionCount, ManagementAction::count());
        $this->assertSame($eventCount, ManagementActionEvent::count());
        $this->assertSame($before, $snapshot(), 'Measuring effectiveness must not mutate any action.');
    }

    public function test_the_effectiveness_service_is_never_given_a_mutating_dependency(): void
    {
        $constructor = (new ReflectionClass(ManagementActionEffectivenessService::class))->getConstructor();

        $this->assertNotNull($constructor);

        $types = array_map(
            fn (ReflectionParameter $parameter) => (string) $parameter->getType(),
            $constructor->getParameters(),
        );

        foreach ($types as $type) {
            $this->assertStringNotContainsString('ManagementActionService', $type);
        }
    }

    // ------------------------------------------------------------------
    // Workflow reflection
    // ------------------------------------------------------------------

    public function test_a_completed_workflow_is_reflected_in_the_effectiveness_metrics(): void
    {
        Notification::fake();

        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $action = $this->makeAction($org, $branch, $user, ['assigned_to' => $user->id]);

        $this->assertSame(0, $this->reportFor($user)['cohort']['metrics']['completed']);

        $this->actingAs($user)->post(route('ai.actions.start', $action))->assertRedirect();
        $this->actingAs($user)->post(route('ai.actions.complete', $action), [
            'completion_notes' => 'Reviewed and resolved.',
        ])->assertRedirect();

        $metrics = $this->reportFor($user)['cohort']['metrics'];

        $this->assertSame(1, $metrics['completed']);
        $this->assertSame(0, $metrics['unresolved']);
        $this->assertSame(0, $metrics['unassigned'], 'A completed action is no longer an ownership gap.');
        $this->assertSame(100.0, $metrics['completion_rate']);
    }

    // ------------------------------------------------------------------
    // Scope enforcement
    // ------------------------------------------------------------------

    public function test_actions_from_another_organization_are_never_measured(): void
    {
        $mine = $this->makeOrganization();
        $theirs = $this->makeOrganization();
        $myBranch = $this->branch($mine);
        $user = $this->viewer($mine, $myBranch);

        $this->makeAction($mine, $myBranch, $user);
        $foreign = $this->makeAction($theirs, $this->branch($theirs), $this->viewer($theirs, $this->branch($theirs)));

        $report = $this->reportFor($user);

        $this->assertSame(1, $report['cohort']['metrics']['total']);
        $this->assertSame(1, $report['live']['metrics']['total']);

        $this->actingAs($user)
            ->get(route('ai.actions.effectiveness'))
            ->assertOk()
            ->assertDontSee($foreign->title);
    }

    public function test_a_foreign_branch_filter_is_forbidden(): void
    {
        $mine = $this->makeOrganization();
        $theirs = $this->makeOrganization();
        $user = $this->viewer($mine, $this->branch($mine));
        $foreignBranch = $this->branch($theirs);

        $this->actingAs($user)
            ->get(route('ai.actions.effectiveness', ['branch_id' => $foreignBranch->id]))
            ->assertForbidden();
    }

    public function test_a_branch_outside_the_user_own_assignments_is_forbidden(): void
    {
        $org = $this->makeOrganization();
        $mine = $this->branch($org);
        $other = $this->branch($org);
        $user = $this->viewer($org, $mine);

        $this->actingAs($user)
            ->get(route('ai.actions.effectiveness', ['branch_id' => $other->id]))
            ->assertForbidden();
    }

    public function test_an_organization_wide_user_may_filter_by_any_of_their_own_branches(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org); // organization-wide reach

        $this->makeAction($org, $branch, $user);

        $response = $this->actingAs($user)
            ->get(route('ai.actions.effectiveness', ['branch_id' => $branch->id]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('report')['cohort']['metrics']['total']);
    }

    public function test_a_foreign_assignee_filter_is_forbidden(): void
    {
        $mine = $this->makeOrganization();
        $theirs = $this->makeOrganization();
        $user = $this->viewer($mine, $this->branch($mine));
        $outsider = $this->viewer($theirs, $this->branch($theirs));

        $this->actingAs($user)
            ->get(route('ai.actions.effectiveness', ['assigned_to' => (string) $outsider->id]))
            ->assertForbidden();
    }

    public function test_an_invalid_period_or_filter_is_rejected_rather_than_silently_ignored(): void
    {
        $org = $this->makeOrganization();
        $user = $this->viewer($org, $this->branch($org));

        $this->actingAs($user)
            ->get(route('ai.actions.effectiveness', ['period' => 'next_century']))
            ->assertSessionHasErrors('period');

        $this->actingAs($user)
            ->get(route('ai.actions.effectiveness', ['source_type' => 'telepathy']))
            ->assertSessionHasErrors('source_type');

        $this->actingAs($user)
            ->get(route('ai.actions.effectiveness', ['period' => 'custom']))
            ->assertSessionHasErrors('from');
    }

    // ------------------------------------------------------------------
    // Page integration
    // ------------------------------------------------------------------

    public function test_the_page_labels_every_figure_as_a_measurement(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        // The breakdowns only render their neutrality notes when there is at
        // least one row, so the page needs real cohort data.
        $this->makeAction($org, $branch, $user, ['assigned_to' => $user->id]);
        $this->makeAction($org, $branch, $user);

        $this->actingAs($user)
            ->get(route('ai.actions.effectiveness'))
            ->assertOk()
            ->assertSee('Observed fact')
            ->assertSee('is a prediction, an advisory or an evaluation of any person.')
            ->assertSee('No branch is ranked, scored or compared against another.')
            ->assertSee('does not score, rate or rank any employee');
    }

    public function test_the_page_renders_the_bounded_record_list(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $action = $this->makeAction($org, $branch, $user, ['title' => 'Follow up the arrears account']);

        $this->actingAs($user)
            ->get(route('ai.actions.effectiveness'))
            ->assertOk()
            ->assertSee('Follow up the arrears account')
            ->assertSee(route('ai.actions.show', $action));
    }

    public function test_the_intelligence_center_reports_the_effectiveness_measurements(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $this->makeAction($org, $branch, $user, [
            'assigned_to' => $user->id,
            'status' => ManagementActionStatus::Completed->value,
        ]);

        $panel = app(ManagementIntelligenceCenterService::class)
            ->overview(app(AiContextBuilderService::class)->build($user))['management_follow_up'];

        $this->assertTrue($panel['available']);
        $this->assertTrue($panel['effectiveness']['available']);
        $this->assertSame(1, $panel['effectiveness']['total_actions']);
        $this->assertSame(0, $panel['effectiveness']['unresolved_actions']);
        $this->assertSame(100.0, $panel['effectiveness']['completion_rate']);

        $this->actingAs($user)
            ->get(route('ai.intelligence-center.index'))
            ->assertOk()
            ->assertSee('Action effectiveness');
    }

    public function test_the_intelligence_center_hides_effectiveness_without_the_capability(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch, 'Secretary');

        $overview = app(ManagementIntelligenceCenterService::class)
            ->overview(app(AiContextBuilderService::class)->build($user));

        $this->assertFalse($overview['management_follow_up']['available']);
    }

    public function test_the_center_panel_is_bounded_to_the_recent_completed_limit(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        foreach (range(1, 8) as $index) {
            $this->makeAction($org, $branch, $user, ['status' => ManagementActionStatus::Completed->value]);
        }

        $panel = app(ManagementActionEffectivenessService::class)
            ->centerPanel(app(AiContextBuilderService::class)->build($user));

        $this->assertSame(8, $panel['total_actions'], 'The measurement counts everything.');
        $this->assertLessThanOrEqual(
            ManagementActionEffectivenessService::RECENT_COMPLETED_LIMIT,
            $panel['recently_completed']->count(),
            'The preview list stays bounded.',
        );
    }

    // ------------------------------------------------------------------
    // Action detail lifecycle
    // ------------------------------------------------------------------

    public function test_an_action_detail_page_shows_factual_lifecycle_measurements(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $action = $this->makeAction($org, $branch, $user, ['assigned_to' => $user->id]);

        $this->actingAs($user)->post(route('ai.actions.start', $action))->assertRedirect();
        $this->actingAs($user)->post(route('ai.actions.complete', $action), ['completion_notes' => 'Done.'])->assertRedirect();

        $this->actingAs($user)
            ->get(route('ai.actions.show', $action))
            ->assertOk()
            ->assertSee('Lifecycle measurements')
            ->assertSee('Total age')
            ->assertSee('Measured up to the recorded closure.');
    }

    public function test_lifecycle_milestones_come_from_the_append_only_timeline(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $action = $this->makeAction($org, $branch, $user);

        foreach ([
            ['created', '2026-06-01 08:00:00'],
            ['started', '2026-06-03 08:00:00'],
            ['completed', '2026-06-09 08:00:00'],
        ] as [$event, $when]) {
            ManagementActionEvent::create([
                'management_action_id' => $action->id,
                'organization_id' => $org->id,
                'actor_id' => $user->id,
                'event' => $event,
                'created_at' => CarbonImmutable::parse($when),
            ]);
        }

        $lifecycle = app(ManagementActionEffectivenessService::class)
            ->lifecycle($action->fresh(), $action->events()->get());

        $this->assertTrue($lifecycle['available']);
        $this->assertSame('2026-06-01 08:00:00', $lifecycle['created_at']);
        $this->assertSame('2026-06-03 08:00:00', $lifecycle['started_at']);
        $this->assertSame('2026-06-09 08:00:00', $lifecycle['completed_at']);
        $this->assertSame(2.0, $lifecycle['time_to_start_days']);
        $this->assertSame(8.0, $lifecycle['time_to_complete_days']);
        $this->assertSame('closure', $lifecycle['total_age_measured_to']);
        $this->assertSame(1, $lifecycle['event_counts']['completed']);
    }

    public function test_lifecycle_falls_back_to_the_action_timestamps_and_reports_null_when_unknown(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->viewer($org, $branch);

        $action = $this->makeAction($org, $branch, $user);

        $lifecycle = app(ManagementActionEffectivenessService::class)
            ->lifecycle($action, $action->events()->get());

        $this->assertTrue($lifecycle['available']);
        $this->assertNull($lifecycle['started_at']);
        $this->assertNull($lifecycle['time_to_start_days']);
        $this->assertNull($lifecycle['time_to_complete_days']);
        $this->assertSame('now', $lifecycle['total_age_measured_to'], 'An open action is still ageing.');
        $this->assertNotNull($lifecycle['total_age_days']);
    }
}
