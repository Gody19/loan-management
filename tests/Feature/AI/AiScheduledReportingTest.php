<?php

namespace Tests\Feature\AI;

use App\AI\DTOs\AiContextData;
use App\AI\Reporting\Scheduling\AiReportScheduleRunner;
use App\AI\Reporting\Scheduling\AiReportScheduleService;
use App\AI\Reporting\Scheduling\ReportScheduleDigestService;
use App\AI\Reporting\Services\ReportPeriodService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\ReportPeriodType;
use App\Enums\ReportScheduleFrequency;
use App\Enums\ReportScheduleRecipientMode;
use App\Enums\ReportScheduleRunStatus;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\AiIntelligenceReport;
use App\Models\AiReportSchedule;
use App\Models\AiReportScheduleRun;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Phase 12.1 Scheduled Management Reporting & Executive Intelligence Digests.
 *
 * The phase is verified against its load-bearing guarantees: recurring reports
 * cover only *completed* periods resolved in the schedule's own timezone, the
 * closed frequency vocabulary can never be bypassed, every schedule is
 * tenant/branch scoped and capability gated, the owner-derived execution
 * context can never widen access, a schedule can produce at most one report per
 * period, delivery re-authorizes every recipient, and a failure is isolated and
 * recorded rather than presented as a report.
 *
 * Two invariants are asserted throughout and never relaxed:
 *  1. scheduling is read-only over the business data — it never creates,
 *     updates or deletes a loan, member or journal entry;
 *  2. the AI never decides anything — the deterministic Phase 12.0 report is
 *     authoritative and the narrative is advisory prose only.
 */
class AiScheduledReportingTest extends AiTestCase
{
    /** Roles granted the scheduling capability by the seeder (Phase 12.1). */
    private const SCHEDULING_ROLES = ['Organization Administrator', 'Branch Manager', 'Auditor'];

    /** Roles that may read reports but must never configure recurring delivery. */
    private const READ_ONLY_ROLES = [
        'Loan Officer', 'Credit Officer', 'Collection Officer', 'Treasurer', 'Accountant',
    ];

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function manager(Organization $org, ?Branch $branch = null, string $role = 'Organization Administrator'): User
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
    private function makeSchedule(Organization $org, User $owner, array $overrides = []): AiReportSchedule
    {
        /** @var AiReportScheduleService $service */
        $service = app(AiReportScheduleService::class);
        $context = app(AiContextBuilderService::class)->build($owner);

        return $service->create($context, $owner, array_merge([
            'name' => 'Recurring report',
            'report_type' => ReportType::ExecutivePortfolio->value,
            'frequency' => ReportScheduleFrequency::Daily->value,
            'run_time' => '06:00',
            'timezone' => 'UTC',
            'recipient_mode' => ReportScheduleRecipientMode::OrganizationManagers->value,
            'include_narrative' => false,
            'branch_id' => null,
        ], $overrides));
    }

    private function due(AiReportSchedule $schedule): AiReportSchedule
    {
        $schedule->update(['next_run_at' => CarbonImmutable::now()->subMinute()]);

        return $schedule->refresh();
    }

    private function context(User $user): AiContextData
    {
        return app(AiContextBuilderService::class)->build($user);
    }

    // ------------------------------------------------------------------
    // Completed-period engine
    // ------------------------------------------------------------------

    public function test_yesterday_period_is_the_previous_completed_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));

        $period = app(ReportPeriodService::class)->resolve('yesterday');

        $this->assertSame('2026-10-14', $period->start);
        $this->assertSame('2026-10-14', $period->end);
        $this->assertSame('2026-10-13', $period->previousStart);
        $this->assertTrue(ReportPeriodType::Yesterday->isCompleted());
    }

    public function test_previous_week_period_is_the_previous_completed_week(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));

        $period = app(ReportPeriodService::class)->resolve('previous_week');

        $this->assertSame('2026-10-05', $period->start);
        $this->assertSame('2026-10-11', $period->end);
        $this->assertTrue(ReportPeriodType::PreviousWeek->isCompleted());
    }

    public function test_every_schedule_frequency_maps_to_a_completed_period(): void
    {
        foreach (ReportScheduleFrequency::cases() as $frequency) {
            $this->assertTrue(
                $frequency->periodType()->isCompleted(),
                "Frequency {$frequency->value} must map to a completed period.",
            );
        }
    }

    public function test_in_progress_periods_are_not_completed(): void
    {
        foreach ([ReportPeriodType::Today, ReportPeriodType::ThisWeek, ReportPeriodType::ThisMonth, ReportPeriodType::ThisQuarter, ReportPeriodType::ThisYear, ReportPeriodType::Custom] as $period) {
            $this->assertFalse($period->isCompleted(), "{$period->value} must not be a completed period.");
        }
    }

    public function test_the_scheduled_period_is_resolved_in_the_schedule_timezone(): void
    {
        // 02:00 UTC on the 15th is still the 14th in Los Angeles, so "yesterday"
        // there is the 13th — the schedule's own day, not the server's.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 02:00:00', 'UTC'));

        $org = $this->makeOrganization();
        $owner = $this->manager($org);

        $schedule = $this->makeSchedule($org, $owner, ['timezone' => 'America/Los_Angeles']);

        $this->assertSame('2026-10-13', $schedule->period()->start);
    }

    // ------------------------------------------------------------------
    // Deterministic forward-only next run
    // ------------------------------------------------------------------

    public function test_next_run_is_always_strictly_in_the_future_for_every_frequency(): void
    {
        $after = CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC');

        foreach (ReportScheduleFrequency::cases() as $frequency) {
            $next = $frequency->nextRunAfter(
                $after,
                '06:00',
                $frequency->requiresWeekday() ? 1 : null,
                $frequency->requiresDayOfMonth() ? 1 : null,
            );

            $this->assertTrue($next->greaterThan($after), "{$frequency->value} must advance.");
        }
    }

    public function test_monthly_next_run_clamps_a_day_beyond_the_month_length(): void
    {
        $next = ReportScheduleFrequency::Monthly->nextRunAfter(
            CarbonImmutable::parse('2026-01-31 12:00:00', 'UTC'),
            '06:00',
            null,
            31,
        );

        $this->assertSame('2026-02-28 06:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_a_schedule_time_outside_the_clock_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ReportScheduleFrequency::Daily->nextRunAfter(CarbonImmutable::now(), '25:00');
    }

    // ------------------------------------------------------------------
    // Capability and RBAC
    // ------------------------------------------------------------------

    public function test_scheduling_roles_hold_the_capability_and_read_only_roles_do_not(): void
    {
        foreach (self::SCHEDULING_ROLES as $role) {
            $user = $this->user($role);

            $this->assertTrue($user->can('ai.reports.schedule'), "{$role} must hold ai.reports.schedule.");
            $this->assertTrue($user->can('ai.reports.view'), "{$role} must also hold ai.reports.view.");
        }

        foreach (self::READ_ONLY_ROLES as $role) {
            $user = $this->user($role);

            $this->assertTrue($user->can('ai.reports.view'), "{$role} may read reports.");
            $this->assertFalse($user->can('ai.reports.schedule'), "{$role} must not configure scheduling.");
        }
    }

    public function test_vicoba_member_and_secretary_are_denied_the_whole_scheduling_surface(): void
    {
        $org = $this->makeOrganization();

        foreach (['Secretary', 'VICOBA Member'] as $role) {
            $user = $this->user($role);
            $user->organizations()->attach($org->id);

            $this->actingAs($user)->post(route('ai.reports.schedules.store'), [
                'name' => 'x',
                'report_type' => ReportType::ExecutivePortfolio->value,
                'frequency' => ReportScheduleFrequency::Daily->value,
                'run_time' => '06:00',
                'recipient_mode' => ReportScheduleRecipientMode::OrganizationManagers->value,
            ])->assertForbidden();
        }
    }

    public function test_creating_a_schedule_requires_the_scheduling_capability(): void
    {
        $org = $this->makeOrganization();
        $officer = $this->staff($org, 'Loan Officer');

        $this->expectException(InvalidArgumentException::class);

        $this->makeSchedule($org, $officer);
    }

    public function test_accounting_report_schedule_requires_the_accounting_capability(): void
    {
        $org = $this->makeOrganization();

        // A reader with the scheduling grant but no accounting capability must
        // still be refused an accounting schedule, exactly like a manual run.
        $owner = $this->staff($org, 'Loan Officer');
        $owner->givePermissionTo('ai.reports.schedule');

        $this->assertTrue($owner->can('ai.reports.schedule'));
        $this->assertFalse($owner->can('ai.accounting.view'));

        $this->expectException(InvalidArgumentException::class);

        $this->makeSchedule($org, $owner, ['report_type' => ReportType::AccountingIntelligence->value]);
    }

    public function test_a_super_administrator_may_schedule_the_accounting_report(): void
    {
        $org = $this->makeOrganization();
        $admin = $this->superAdmin();
        $admin->organizations()->attach($org->id);

        $schedule = $this->makeSchedule($org, $admin, ['report_type' => ReportType::AccountingIntelligence->value]);

        $this->assertSame(ReportType::AccountingIntelligence, $schedule->report_type);
    }

    // ------------------------------------------------------------------
    // Tenant and branch isolation
    // ------------------------------------------------------------------

    public function test_a_foreign_schedule_is_not_disclosed(): void
    {
        $orgA = $this->makeOrganization();
        $orgB = $this->makeOrganization();

        $owner = $this->manager($orgA);
        $schedule = $this->makeSchedule($orgA, $owner);

        $intruder = $this->manager($orgB);

        /** @var AiReportScheduleService $service */
        $service = app(AiReportScheduleService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->findManageable($this->context($intruder), (int) $schedule->id);
    }

    public function test_branch_of_another_organization_cannot_be_scheduled(): void
    {
        $orgA = $this->makeOrganization();
        $orgB = $this->makeOrganization();
        $foreignBranch = $this->branch($orgB);

        $owner = $this->manager($orgA);

        $this->expectException(InvalidArgumentException::class);

        $this->makeSchedule($orgA, $owner, ['branch_id' => (string) $foreignBranch->id]);
    }

    public function test_specific_recipients_must_be_authorized_users(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);

        // A user in another organization, even with the capability, is not a
        // valid recipient of this organization's schedule.
        $foreignOrg = $this->makeOrganization();
        $foreign = $this->manager($foreignOrg);

        $this->expectException(InvalidArgumentException::class);

        $this->makeSchedule($org, $owner, [
            'recipient_mode' => ReportScheduleRecipientMode::SpecificUsers->value,
            'recipients' => [(int) $foreign->id],
        ]);
    }

    public function test_specific_recipient_mode_requires_at_least_one_recipient(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);

        $this->expectException(InvalidArgumentException::class);

        $this->makeSchedule($org, $owner, [
            'recipient_mode' => ReportScheduleRecipientMode::SpecificUsers->value,
            'recipients' => [],
        ]);
    }

    // ------------------------------------------------------------------
    // Lifecycle
    // ------------------------------------------------------------------

    public function test_an_authorized_manager_creates_a_schedule_and_it_is_audited(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);

        $schedule = $this->makeSchedule($org, $owner, ['name' => 'Weekly board pack']);

        $this->assertDatabaseHas('ai_report_schedules', [
            'id' => $schedule->id,
            'organization_id' => $org->id,
            'name' => 'Weekly board pack',
            'is_active' => true,
        ]);
        $this->assertTrue($schedule->next_run_at->isFuture());
        $this->assertTrue(AuditLog::where('event', 'ai.report_schedule.created')->exists());
    }

    public function test_update_recomputes_the_next_run_strictly_forward(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));

        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $schedule = $this->makeSchedule($org, $owner);

        /** @var AiReportScheduleService $service */
        $service = app(AiReportScheduleService::class);

        $service->update($this->context($owner), $owner, $schedule, [
            'frequency' => ReportScheduleFrequency::Weekly->value,
            'weekday' => 1,
        ]);

        $schedule->refresh();

        $this->assertSame(ReportScheduleFrequency::Weekly, $schedule->frequency);
        $this->assertSame(1, (int) $schedule->weekday);
        $this->assertTrue($schedule->next_run_at->isFuture());
    }

    public function test_toggle_disables_and_reenables_a_schedule(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $schedule = $this->makeSchedule($org, $owner);

        /** @var AiReportScheduleService $service */
        $service = app(AiReportScheduleService::class);
        $context = $this->context($owner);

        $service->setActive($context, $owner, $schedule, false);
        $schedule->refresh();
        $this->assertFalse($schedule->is_active);
        $this->assertNull($schedule->next_run_at);

        $service->setActive($context, $owner, $schedule, true);
        $schedule->refresh();
        $this->assertTrue($schedule->is_active);
        $this->assertNotNull($schedule->next_run_at);
        $this->assertTrue($schedule->next_run_at->isFuture());
    }

    // ------------------------------------------------------------------
    // Execution
    // ------------------------------------------------------------------

    public function test_a_due_schedule_produces_a_completed_report_and_a_run(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $schedule = $this->due($this->makeSchedule($org, $owner));

        $summary = app(AiReportScheduleRunner::class)->runDue();

        $this->assertSame(1, $summary['completed']);

        $run = AiReportScheduleRun::where('ai_report_schedule_id', $schedule->id)->firstOrFail();

        $this->assertSame(ReportScheduleRunStatus::Completed, $run->status);
        $this->assertNotNull($run->ai_intelligence_report_id);

        $report = AiIntelligenceReport::findOrFail($run->ai_intelligence_report_id);
        $this->assertSame(ReportStatus::Completed, $report->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.report_schedule.executed']);
    }

    public function test_running_the_same_period_twice_produces_only_one_report(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $schedule = $this->makeSchedule($org, $owner);

        $runner = app(AiReportScheduleRunner::class);

        $this->due($schedule);
        $runner->runDue();

        // Force it due again in the same period: the execution key must win.
        $this->due($schedule);
        $runner->runDue();

        $this->assertSame(1, AiReportScheduleRun::where('ai_report_schedule_id', $schedule->id)->count());
        $this->assertSame(1, AiIntelligenceReport::where('organization_id', $org->id)->count());
    }

    public function test_a_manual_run_uses_the_idempotent_path_and_never_modifies_the_schedule(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $schedule = $this->makeSchedule($org, $owner, [
            'frequency' => ReportScheduleFrequency::Monthly->value,
            'day_of_month' => 1,
        ]);

        $before = $schedule->only(['report_type', 'frequency', 'run_time', 'recipient_mode', 'is_active']);

        $runner = app(AiReportScheduleRunner::class);

        $first = $runner->run($schedule, 'manual', $owner);
        $this->assertSame(ReportScheduleRunStatus::Completed, $first->status);
        $this->assertFalse($first->replayed);

        $second = $runner->run($schedule->refresh(), 'manual', $owner);
        $this->assertTrue($second->replayed);
        $this->assertSame((int) $first->ai_intelligence_report_id, (int) $second->ai_intelligence_report_id);

        $this->assertSame(1, AiReportScheduleRun::where('ai_report_schedule_id', $schedule->id)->count());
        $this->assertEquals($before, $schedule->refresh()->only(array_keys($before)));
    }

    public function test_execution_fails_when_the_owner_loses_the_reporting_capability(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $schedule = $this->makeSchedule($org, $owner);

        // The owner is demoted after creating the schedule: the run must fail
        // rather than execute under some broader system authority.
        $owner->syncRoles([]);

        $this->due($schedule);
        app(AiReportScheduleRunner::class)->runDue();

        $run = AiReportScheduleRun::where('ai_report_schedule_id', $schedule->id)->firstOrFail();

        $this->assertSame(ReportScheduleRunStatus::Failed, $run->status);
        $this->assertNull($run->ai_intelligence_report_id);
        $this->assertNotNull($run->failure_reason);
        $this->assertSame(0, AiIntelligenceReport::where('organization_id', $org->id)->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.report_schedule.failed']);
    }

    public function test_one_failing_schedule_does_not_abort_the_other_schedules(): void
    {
        $org = $this->makeOrganization();
        $brokenOwner = $this->manager($org);
        $healthyOwner = $this->manager($org);

        $broken = $this->makeSchedule($org, $brokenOwner, ['name' => 'Broken']);
        $healthy = $this->makeSchedule($org, $healthyOwner, [
            'name' => 'Healthy',
            'report_type' => ReportType::OperationalIntelligence->value,
        ]);

        $brokenOwner->syncRoles([]);

        $this->due($broken);
        $this->due($healthy);

        $summary = app(AiReportScheduleRunner::class)->runDue();

        $this->assertSame(1, $summary['failed']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(ReportScheduleRunStatus::Failed, AiReportScheduleRun::where('ai_report_schedule_id', $broken->id)->firstOrFail()->status);
        $this->assertSame(ReportScheduleRunStatus::Completed, AiReportScheduleRun::where('ai_report_schedule_id', $healthy->id)->firstOrFail()->status);
    }

    public function test_the_execution_key_is_deterministic_per_schedule_and_period(): void
    {
        $keyA = AiReportScheduleRun::executionKeyFor(7, 'yesterday', '2026-10-14', '2026-10-14');
        $keyB = AiReportScheduleRun::executionKeyFor(7, 'yesterday', '2026-10-14', '2026-10-14');
        $keyC = AiReportScheduleRun::executionKeyFor(8, 'yesterday', '2026-10-14', '2026-10-14');

        $this->assertSame($keyA, $keyB);
        $this->assertNotSame($keyA, $keyC);
    }

    // ------------------------------------------------------------------
    // Delivery and digest
    // ------------------------------------------------------------------

    public function test_only_currently_authorized_recipients_are_delivered_to(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $second = $this->manager($org, null, 'Branch Manager');
        $secretary = $this->staff($org, 'Secretary');

        $schedule = $this->makeSchedule($org, $owner);

        /** @var AiReportScheduleService $service */
        $service = app(AiReportScheduleService::class);
        $recipients = $service->resolveRecipients($schedule);

        $this->assertTrue($recipients->contains($owner->id));
        $this->assertTrue($recipients->contains($second->id));
        $this->assertFalse($recipients->contains($secretary->id));

        // Revoking the second recipient's capability excludes them immediately.
        $second->syncRoles([]);

        $this->assertFalse($service->resolveRecipients($schedule)->contains($second->id));
    }

    public function test_delivery_notifies_each_authorized_recipient_once(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $second = $this->manager($org, null, 'Branch Manager');
        $schedule = $this->due($this->makeSchedule($org, $owner));

        app(AiReportScheduleRunner::class)->runDue();

        $this->assertSame(1, $owner->fresh()->notifications()->count());
        $this->assertSame(1, $second->fresh()->notifications()->count());
    }

    public function test_a_branch_scoped_schedule_only_notifies_the_branch(): void
    {
        $org = $this->makeOrganization();
        $branchA = $this->branch($org);
        $branchB = $this->branch($org);

        $owner = $this->manager($org, $branchA);
        $sameBranch = $this->manager($org, $branchA, 'Branch Manager');
        $otherBranch = $this->manager($org, $branchB, 'Branch Manager');

        $schedule = $this->due($this->makeSchedule($org, $owner, ['branch_id' => (string) $branchA->id]));

        app(AiReportScheduleRunner::class)->runDue();

        $this->assertSame(1, $owner->fresh()->notifications()->count());
        $this->assertSame(1, $sameBranch->fresh()->notifications()->count());
        $this->assertSame(0, $otherBranch->fresh()->notifications()->count());
    }

    public function test_disabling_notification_still_produces_the_report(): void
    {
        config(['intelligence-reporting.scheduling.notify' => false]);

        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $schedule = $this->due($this->makeSchedule($org, $owner));

        $summary = app(AiReportScheduleRunner::class)->runDue();

        $this->assertSame(1, $summary['completed']);
        $this->assertSame(0, $summary['notifications']);
        $this->assertSame(1, AiReportScheduleRun::where('ai_report_schedule_id', $schedule->id)->where('status', 'completed')->count());
        $this->assertSame(0, $owner->fresh()->notifications()->count());
    }

    public function test_the_notification_links_to_the_persisted_report(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $schedule = $this->due($this->makeSchedule($org, $owner));

        app(AiReportScheduleRunner::class)->runDue();

        $notification = $owner->fresh()->notifications()->firstOrFail();
        $run = AiReportScheduleRun::where('ai_report_schedule_id', $schedule->id)->firstOrFail();

        $this->assertSame((int) $run->ai_intelligence_report_id, (int) data_get($notification->data, 'report_id'));
        $this->assertSame(route('ai.reports.show', $run->ai_intelligence_report_id), data_get($notification->data, 'url'));
        $this->assertSame('scheduled_intelligence_report', data_get($notification->data, 'type'));
    }

    public function test_the_digest_preserves_classification_and_labels_the_narrative_as_advisory(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $schedule = $this->makeSchedule($org, $owner, ['include_narrative' => true]);

        $report = new AiIntelligenceReport([
            'organization_id' => $org->id,
            'report_type' => ReportType::ExecutivePortfolio->value,
            'status' => ReportStatus::Completed->value,
            'period_type' => ReportPeriodType::Yesterday->value,
            'period_start' => '2026-10-14',
            'period_end' => '2026-10-14',
            'data_through' => '2026-10-14 23:00:00',
            'report_data' => [
                'sections' => [[
                    'key' => 'portfolio',
                    'title' => 'Portfolio',
                    'facts' => [[
                        'classification' => 'fact',
                        'classification_label' => 'Fact',
                        'label' => 'Outstanding principal',
                        'value' => 500000,
                        'unit' => 'TZS',
                    ]],
                    'advisories' => [[
                        'classification' => 'advisory',
                        'classification_label' => 'Advisory',
                        'label' => 'Delinquency rising',
                        'value' => null,
                    ]],
                ]],
                'data_quality' => [],
            ],
            'narrative' => [
                'available' => true,
                'summary' => 'Portfolio is stable. This is explanatory prose.',
            ],
        ]);

        $digest = app(ReportScheduleDigestService::class)->build($schedule, $report);

        $this->assertSame('Fact', $digest['facts'][0]['classification_label']);
        $this->assertSame('Advisory', $digest['advisories'][0]['classification_label']);
        $this->assertStringContainsString('AI explanation', (string) $digest['narrative']);
        $this->assertStringContainsString('advisory prose', (string) $digest['narrative']);
    }

    // ------------------------------------------------------------------
    // Scheduler command
    // ------------------------------------------------------------------

    public function test_the_command_runs_due_schedules(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $this->due($this->makeSchedule($org, $owner));

        $this->artisan('ai:run-report-schedules')->assertSuccessful();

        $this->assertSame(1, AiIntelligenceReport::where('organization_id', $org->id)->count());
    }

    public function test_the_command_is_a_noop_when_nothing_is_due(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $this->makeSchedule($org, $owner);

        $this->artisan('ai:run-report-schedules')
            ->expectsOutputToContain('No scheduled management reports are due.')
            ->assertSuccessful();

        $this->assertSame(0, AiReportScheduleRun::count());
    }

    public function test_the_command_respects_the_organization_filter(): void
    {
        $orgA = $this->makeOrganization();
        $orgB = $this->makeOrganization();

        $ownerA = $this->manager($orgA);
        $ownerB = $this->manager($orgB);

        $this->due($this->makeSchedule($orgA, $ownerA));
        $this->due($this->makeSchedule($orgB, $ownerB));

        $this->artisan('ai:run-report-schedules', ['--organization' => $orgA->id])->assertSuccessful();

        $this->assertSame(1, AiIntelligenceReport::where('organization_id', $orgA->id)->count());
        $this->assertSame(0, AiIntelligenceReport::where('organization_id', $orgB->id)->count());
    }

    public function test_the_command_is_a_noop_when_scheduling_is_disabled(): void
    {
        config(['intelligence-reporting.scheduling.enabled' => false]);

        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $this->due($this->makeSchedule($org, $owner));

        $this->artisan('ai:run-report-schedules')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        $this->assertSame(0, AiReportScheduleRun::count());
    }

    // ------------------------------------------------------------------
    // HTTP surface
    // ------------------------------------------------------------------

    public function test_store_creates_a_schedule_over_http(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);

        $response = $this->actingAs($owner)->post(route('ai.reports.schedules.store'), [
            'name' => 'Daily executive pack',
            'report_type' => ReportType::ExecutivePortfolio->value,
            'frequency' => ReportScheduleFrequency::Daily->value,
            'run_time' => '06:30',
            'timezone' => 'UTC',
            'recipient_mode' => ReportScheduleRecipientMode::OrganizationManagers->value,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ai_report_schedules', [
            'organization_id' => $org->id,
            'name' => 'Daily executive pack',
        ]);
    }

    public function test_store_rejects_an_accounting_schedule_without_the_accounting_capability(): void
    {
        $org = $this->makeOrganization();

        // A reader with the scheduling grant but no accounting capability: the
        // accounting report type must not even be offered, let alone stored.
        $owner = $this->staff($org, 'Loan Officer');
        $owner->givePermissionTo('ai.reports.schedule');

        $this->actingAs($owner)->post(route('ai.reports.schedules.store'), [
            'name' => 'Accounting pack',
            'report_type' => ReportType::AccountingIntelligence->value,
            'frequency' => ReportScheduleFrequency::Daily->value,
            'run_time' => '06:30',
            'timezone' => 'UTC',
            'recipient_mode' => ReportScheduleRecipientMode::OrganizationManagers->value,
        ])->assertSessionHasErrors('report_type');

        $this->assertSame(0, AiReportSchedule::where('name', 'Accounting pack')->count());
    }

    public function test_schedule_routes_are_capability_gated(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $schedule = $this->makeSchedule($org, $owner);

        $officer = $this->staff($org, 'Loan Officer');

        $this->actingAs($officer)
            ->post(route('ai.reports.schedules.run', $schedule))
            ->assertForbidden();

        $this->actingAs($officer)
            ->delete(route('ai.reports.schedules.destroy', $schedule))
            ->assertForbidden();
    }

    public function test_run_now_reports_an_idempotent_replay(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->manager($org);
        $schedule = $this->makeSchedule($org, $owner);

        $this->actingAs($owner)
            ->post(route('ai.reports.schedules.run', $schedule))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($owner)
            ->post(route('ai.reports.schedules.run', $schedule))
            ->assertRedirect()
            ->assertSessionHas('info');
    }

    public function test_the_dashboard_lists_only_schedules_within_the_trusted_scope(): void
    {
        $orgA = $this->makeOrganization();
        $orgB = $this->makeOrganization();

        $ownerA = $this->manager($orgA);
        $ownerB = $this->manager($orgB);

        $this->makeSchedule($orgA, $ownerA, ['name' => 'Org A only schedule']);

        $this->actingAs($ownerA)
            ->get(route('ai.reports.index'))
            ->assertOk()
            ->assertSee('Org A only schedule');

        $this->actingAs($ownerB)
            ->get(route('ai.reports.index'))
            ->assertOk()
            ->assertDontSee('Org A only schedule');
    }

    // ------------------------------------------------------------------
    // Safety
    // ------------------------------------------------------------------

    public function test_scheduling_never_mutates_a_business_record(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $owner = $this->manager($org, $branch);

        $member = $this->member($org);

        Loan::factory()->active()->create([
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
        ]);

        $loans = Loan::count();
        $members = Member::count();
        $journals = JournalEntry::count();
        $reportCount = AiIntelligenceReport::count();

        $schedule = $this->due($this->makeSchedule($org, $owner, ['branch_id' => (string) $branch->id]));

        app(AiReportScheduleRunner::class)->runDue();

        $this->assertSame($loans, Loan::count());
        $this->assertSame($members, Member::count());
        $this->assertSame($journals, JournalEntry::count());
        $this->assertSame($reportCount + 1, AiIntelligenceReport::count());
        $this->assertDatabaseHas('ai_report_schedule_runs', [
            'ai_report_schedule_id' => $schedule->id,
            'status' => 'completed',
        ]);
    }
}
