<?php

namespace Tests\Feature\AI;

use App\AI\ManagementCenter\Services\ManagementIntelligenceCenterService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\PredictiveInsightStatus;
use App\Enums\PredictiveInsightType;
use App\Enums\ProactiveInsightSeverity;
use App\Enums\ProactiveInsightSource;
use App\Enums\ProactiveInsightStatus;
use App\Enums\ProactiveInsightType;
use App\Enums\ReportDatumClassification;
use App\Enums\ReportPeriodType;
use App\Enums\ReportScheduleFrequency;
use App\Enums\ReportScheduleRecipientMode;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\AiInsight;
use App\Models\AiIntelligenceReport;
use App\Models\AiPrediction;
use App\Models\AiReportSchedule;
use App\Models\AiReportScheduleRun;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Phase 12.2 Unified Management Intelligence Center & Executive Review Workspace.
 *
 * The center is verified against its load-bearing guarantees: it is a read-only
 * presentation surface that never generates a prediction, insight, report,
 * schedule run or business record; every datum keeps its explicit fact / trend /
 * prediction / advisory classification; every section is capability gated; and
 * the trusted organization/branch scope is never widened by a request filter.
 *
 * The AI explanation is asserted to be optional, advisory and non-fatal: the
 * deterministic center remains fully functional when the provider is
 * unavailable.
 */
class AiManagementIntelligenceCenterTest extends AiTestCase
{
    /** Roles that must never reach the center (no ai.* capability at all). */
    private const BLIND_ROLES = ['Secretary', 'VICOBA Member'];

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function centerUser(Organization $org, ?Branch $branch = null, string $role = 'Organization Administrator'): User
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
    private function makeInsight(Organization $org, ?Branch $branch = null, array $overrides = []): AiInsight
    {
        return AiInsight::create(array_merge([
            'organization_id' => $org->id,
            'branch_id' => $branch?->id,
            'type' => ProactiveInsightType::OverdueLoan->value,
            'severity' => ProactiveInsightSeverity::Warning->value,
            'title' => 'Insight '.uniqid(),
            'summary' => 'A deterministic advisory.',
            'recommendation' => 'Review the underlying record.',
            'source_type' => ProactiveInsightSource::Loan->value,
            'dedup_key' => 'insight-'.uniqid(),
            'status' => ProactiveInsightStatus::New->value,
            'generated_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeReport(Organization $org, ?Branch $branch, User $requester, array $overrides = []): AiIntelligenceReport
    {
        return AiIntelligenceReport::create(array_merge([
            'organization_id' => $org->id,
            'branch_id' => $branch?->id,
            'report_type' => ReportType::ExecutivePortfolio->value,
            'status' => ReportStatus::Completed->value,
            'period_type' => ReportPeriodType::ThisMonth->value,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'data_through' => now(),
            'generated_at' => now(),
            'requested_by' => $requester->id,
            'report_data' => ['sections' => []],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSchedule(Organization $org, User $owner, array $overrides = []): AiReportSchedule
    {
        return AiReportSchedule::create(array_merge([
            'organization_id' => $org->id,
            'branch_id' => null,
            'name' => 'Schedule '.uniqid(),
            'report_type' => ReportType::ExecutivePortfolio->value,
            'frequency' => ReportScheduleFrequency::Daily->value,
            'run_time' => '06:00',
            'timezone' => 'UTC',
            'recipient_mode' => ReportScheduleRecipientMode::OrganizationManagers->value,
            'recipients' => [],
            'include_narrative' => false,
            'is_active' => true,
            'next_run_at' => now()->addDay(),
            'created_by' => $owner->id,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRun(AiReportSchedule $schedule, User $requester, array $overrides = []): AiReportScheduleRun
    {
        $start = now()->subDay()->toDateString();
        $end = now()->subDay()->toDateString();

        return AiReportScheduleRun::create(array_merge([
            'ai_report_schedule_id' => $schedule->id,
            'ai_intelligence_report_id' => null,
            'execution_key' => AiReportScheduleRun::executionKeyFor($schedule->id, ReportPeriodType::Yesterday->value, $start, $end),
            'trigger' => 'scheduled',
            'status' => 'completed',
            'period_type' => ReportPeriodType::Yesterday->value,
            'period_start' => $start,
            'period_end' => $end,
            'timezone' => 'UTC',
            'notifications_sent' => 0,
            'digest_available' => false,
            'requested_by' => $requester->id,
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Access control
    // ------------------------------------------------------------------

    public function test_a_manager_with_intelligence_capabilities_can_view_the_center(): void
    {
        $org = $this->makeOrganization();
        $user = $this->centerUser($org, $this->branch($org));

        $response = $this->actingAs($user)->get(route('ai.intelligence-center.index'));

        $response->assertOk();
        $response->assertSee('Management Intelligence Center');
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('ai.intelligence-center.index'))->assertRedirect(route('login'));
    }

    public function test_roles_without_any_intelligence_capability_are_forbidden(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);

        foreach (self::BLIND_ROLES as $role) {
            $user = $role === 'VICOBA Member'
                ? $this->vicobaUser($org, $this->member($org))
                : $this->centerUser($org, $branch, $role);

            $this->actingAs($user)
                ->get(route('ai.intelligence-center.index'))
                ->assertForbidden();
        }
    }

    // ------------------------------------------------------------------
    // Tenant and branch scope
    // ------------------------------------------------------------------

    public function test_a_foreign_organization_filter_is_forbidden(): void
    {
        $mine = $this->makeOrganization();
        $user = $this->centerUser($mine, $this->branch($mine));

        $theirs = $this->makeOrganization();
        $foreignBranch = $this->branch($theirs);

        $this->actingAs($user)
            ->get(route('ai.intelligence-center.index', ['branch_id' => $foreignBranch->id]))
            ->assertForbidden();
    }

    public function test_an_unassigned_branch_in_the_same_organization_is_forbidden(): void
    {
        $org = $this->makeOrganization();
        $mine = $this->branch($org);
        $theirs = $this->branch($org);
        $user = $this->centerUser($org, $mine, 'Branch Manager');

        $this->actingAs($user)
            ->get(route('ai.intelligence-center.index', ['branch_id' => $theirs->id]))
            ->assertForbidden();
    }

    public function test_the_center_never_exposes_another_tenants_artifacts(): void
    {
        $mine = $this->makeOrganization();
        $myBranch = $this->branch($mine);
        $user = $this->centerUser($mine, $myBranch);

        $theirs = $this->makeOrganization();
        $theirBranch = $this->branch($theirs);
        $theirUser = $this->centerUser($theirs, $theirBranch);

        $foreignInsight = $this->makeInsight($theirs, $theirBranch, ['title' => 'FOREIGN-INSIGHT-TITLE']);
        $foreignSchedule = $this->makeSchedule($theirs, $theirUser, ['name' => 'FOREIGN-SCHEDULE-NAME']);
        $foreignReport = $this->makeReport($theirs, $theirBranch, $theirUser);

        $response = $this->actingAs($user)->get(route('ai.intelligence-center.index'));

        $response->assertOk();
        $response->assertDontSee($foreignInsight->title);
        $response->assertDontSee($foreignSchedule->name);
        $response->assertDontSee(route('ai.reports.show', $foreignReport));
    }

    public function test_branch_scope_limits_the_attention_queue_to_assigned_and_org_wide_insights(): void
    {
        $org = $this->makeOrganization();
        $mine = $this->branch($org);
        $other = $this->branch($org);
        $user = $this->centerUser($org, $mine, 'Branch Manager');

        $assigned = $this->makeInsight($org, $mine, ['title' => 'ASSIGNED-BRANCH-INSIGHT']);
        $orgWide = $this->makeInsight($org, null, ['title' => 'ORG-WIDE-INSIGHT']);
        $foreign = $this->makeInsight($org, $other, ['title' => 'OTHER-BRANCH-INSIGHT']);

        $response = $this->actingAs($user)->get(route('ai.intelligence-center.index'));

        $response->assertOk();
        $response->assertSee($assigned->title);
        $response->assertSee($orgWide->title);
        $response->assertDontSee($foreign->title);
    }

    public function test_the_branch_filter_only_offers_authorized_branches(): void
    {
        $org = $this->makeOrganization();
        $mine = $this->branch($org);
        $other = $this->branch($org);
        $user = $this->centerUser($org, $mine, 'Branch Manager');

        $response = $this->actingAs($user)->get(route('ai.intelligence-center.index'));

        $response->assertOk();
        $response->assertSee($mine->name);
        $response->assertDontSee($other->name);
    }

    // ------------------------------------------------------------------
    // Classification contract
    // ------------------------------------------------------------------

    public function test_every_datum_carries_one_of_the_four_classifications(): void
    {
        $org = $this->makeOrganization();
        $user = $this->centerUser($org, $this->branch($org));
        $context = app(AiContextBuilderService::class)->build($user);

        $overview = app(ManagementIntelligenceCenterService::class)->overview($context, ['period' => 'this_month']);

        $allowed = ReportDatumClassification::values();
        $seen = [];

        $this->assertNotEmpty($overview['executive_summary'], 'An authorized manager must receive a summary.');

        $collect = function (array $items) use ($allowed, &$seen): void {
            foreach ($items as $datum) {
                $this->assertContains($datum['classification'], $allowed);
                $this->assertNotEmpty($datum['classification_label']);
                $seen[$datum['classification']] = true;
            }
        };

        $collect($overview['executive_summary']);

        foreach ($overview['sections'] as $section) {
            $collect($section['items']);
        }

        foreach ($allowed as $classification) {
            $this->assertArrayHasKey($classification, $seen, "The center never presented a {$classification} datum.");
        }
    }

    public function test_the_rendered_center_labels_the_classifications_explicitly(): void
    {
        $org = $this->makeOrganization();
        $user = $this->centerUser($org, $this->branch($org));

        $response = $this->actingAs($user)->get(route('ai.intelligence-center.index'));

        $response->assertOk();
        $response->assertSee('Observed fact');
        $response->assertSee('Trend');
        $response->assertSee('Prediction');
        $response->assertSee('Advisory');
    }

    // ------------------------------------------------------------------
    // Persisted artifacts & read-only behaviour
    // ------------------------------------------------------------------

    public function test_a_persisted_prediction_is_presented_and_never_regenerated(): void
    {
        $org = $this->makeOrganization();
        $user = $this->centerUser($org, $this->branch($org));

        $prediction = AiPrediction::factory()->create([
            'organization_id' => $org->id,
            'type' => PredictiveInsightType::PortfolioForecast,
            'status' => PredictiveInsightStatus::Generated,
            'generated_at' => now()->subHour(),
        ]);

        $before = AiPrediction::count();

        $response = $this->actingAs($user)->get(route('ai.intelligence-center.index'));

        $response->assertOk();
        $response->assertSee(PredictiveInsightType::PortfolioForecast->label());
        $this->assertSame($before, AiPrediction::count(), 'Viewing the center must not generate a prediction.');
        $this->assertSame($prediction->id, AiPrediction::latest('id')->first()?->id);
    }

    public function test_viewing_the_attention_queue_never_changes_an_insight_status(): void
    {
        $org = $this->makeOrganization();
        $user = $this->centerUser($org, $this->branch($org));
        $insight = $this->makeInsight($org, null, ['title' => 'UNREAD-INSIGHT']);

        $this->actingAs($user)->get(route('ai.intelligence-center.index'))->assertOk();

        $this->assertSame(ProactiveInsightStatus::New, $insight->fresh()->status);
    }

    public function test_loading_the_center_creates_no_report_prediction_insight_or_schedule_run(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->centerUser($org, $branch);

        $this->makeInsight($org, $branch);
        $this->makeReport($org, $branch, $user);
        $schedule = $this->makeSchedule($org, $user);
        $this->makeRun($schedule, $user);

        $counts = [
            'reports' => AiIntelligenceReport::count(),
            'predictions' => AiPrediction::count(),
            'insights' => AiInsight::count(),
            'schedules' => AiReportSchedule::count(),
            'runs' => AiReportScheduleRun::count(),
        ];

        $this->actingAs($user)->get(route('ai.intelligence-center.index', ['narrative' => 0]))->assertOk();

        $this->assertSame($counts['reports'], AiIntelligenceReport::count());
        $this->assertSame($counts['predictions'], AiPrediction::count());
        $this->assertSame($counts['insights'], AiInsight::count());
        $this->assertSame($counts['schedules'], AiReportSchedule::count());
        $this->assertSame($counts['runs'], AiReportScheduleRun::count());
    }

    public function test_the_report_library_links_to_the_existing_report_detail_page(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->centerUser($org, $branch);
        $report = $this->makeReport($org, $branch, $user);

        $response = $this->actingAs($user)->get(route('ai.intelligence-center.index'));

        $response->assertOk();
        $response->assertSee(route('ai.reports.show', $report));
    }

    public function test_a_failed_report_is_listed_without_a_view_link(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->centerUser($org, $branch);
        $failed = $this->makeReport($org, $branch, $user, [
            'status' => ReportStatus::Failed->value,
            'failure_reason' => 'The report could not be generated.',
        ]);

        $response = $this->actingAs($user)->get(route('ai.intelligence-center.index'));

        $response->assertOk();
        $response->assertDontSee(route('ai.reports.show', $failed));
    }

    public function test_schedules_are_only_rendered_for_a_scheduling_capability_holder(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);

        $owner = $this->centerUser($org, $branch, 'Organization Administrator');
        $schedule = $this->makeSchedule($org, $owner, ['name' => 'MANAGED-RECURRING-REPORT']);

        $manager = $this->actingAs($owner)->get(route('ai.intelligence-center.index'));
        $manager->assertOk();
        $manager->assertSee('Scheduled reports');
        $manager->assertSee($schedule->name);

        $reader = $this->centerUser($org, $branch, 'Loan Officer');
        $readerResponse = $this->actingAs($reader)->get(route('ai.intelligence-center.index'));
        $readerResponse->assertOk();
        $readerResponse->assertDontSee('Scheduled reports');
        $readerResponse->assertDontSee($schedule->name);
    }

    public function test_a_reader_without_the_accounting_capability_never_sees_accounting_sections(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $reader = $this->centerUser($org, $branch, 'Loan Officer');

        $response = $this->actingAs($reader)->get(route('ai.intelligence-center.index'));

        $response->assertOk();
        $response->assertDontSee('Total income');
        $response->assertDontSee('Trial balance');
    }

    // ------------------------------------------------------------------
    // AI explanation
    // ------------------------------------------------------------------

    public function test_the_ai_explanation_is_optional_and_disabled_by_default(): void
    {
        $org = $this->makeOrganization();
        $user = $this->centerUser($org, $this->branch($org));
        $context = app(AiContextBuilderService::class)->build($user);

        $overview = app(ManagementIntelligenceCenterService::class)->overview($context, []);

        $this->assertNull($overview['narrative'], 'The AI explanation must not be generated unless requested.');
    }

    public function test_the_ai_explanation_is_generated_on_demand_over_the_classified_payload(): void
    {
        $org = $this->makeOrganization();
        $user = $this->centerUser($org, $this->branch($org));

        $response = $this->actingAs($user)->get(route('ai.intelligence-center.index', ['narrative' => 1]));

        $response->assertOk();
        $response->assertSee('AI-generated advisory explanation');
        $response->assertSee('deterministic offline reply', false);
        $response->assertViewHas('overview', function (array $overview): bool {
            return ($overview['narrative']['available'] ?? false) === true
                && ($overview['narrative']['provider'] ?? null) === 'fake';
        });
    }

    public function test_the_center_remains_functional_when_the_ai_explanation_is_unavailable(): void
    {
        $org = $this->makeOrganization();
        $user = $this->centerUser($org, $this->branch($org));

        $this->disableAi();

        $response = $this->actingAs($user)->get(route('ai.intelligence-center.index', ['narrative' => 1]));

        $response->assertOk();
        $response->assertSee('Management Intelligence Center');
        $response->assertSee('AI explanation unavailable');
        $response->assertSee('unaffected');
    }

    public function test_a_custom_period_requires_both_boundaries(): void
    {
        $org = $this->makeOrganization();
        $user = $this->centerUser($org, $this->branch($org));

        $this->actingAs($user)
            ->get(route('ai.intelligence-center.index', ['period' => ReportPeriodType::Custom->value]))
            ->assertSessionHasErrors('from');
    }

    public function test_a_valid_custom_period_is_resolved_and_presented(): void
    {
        $org = $this->makeOrganization();
        $user = $this->centerUser($org, $this->branch($org));

        $response = $this->actingAs($user)->get(route('ai.intelligence-center.index', [
            'period' => ReportPeriodType::Custom->value,
            'from' => '2026-01-01',
            'to' => '2026-01-31',
        ]));

        $response->assertOk();
        $response->assertSee('2026-01-01');
        $response->assertSee('2026-01-31');
    }
}
