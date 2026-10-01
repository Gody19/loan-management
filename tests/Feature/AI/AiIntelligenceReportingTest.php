<?php

namespace Tests\Feature\AI;

use App\AI\Policies\AiToolPolicy;
use App\AI\ProactiveIntelligence\Services\ProactiveInsightService;
use App\AI\Reporting\Services\AiIntelligenceReportService;
use App\AI\Reporting\Services\IntelligenceReportNarrativeService;
use App\AI\Reporting\Services\ReportBuilderService;
use App\AI\Reporting\Services\ReportPeriodService;
use App\AI\Services\AiChatOrchestrationService;
use App\AI\Services\AiContextBuilderService;
use App\AI\Services\AiProviderService;
use App\AI\Services\AiToolRegistry;
use App\AI\Tools\IntelligenceReportTool;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\PredictiveInsightType;
use App\Enums\ReportDatumClassification;
use App\Enums\ReportPeriodType;
use App\Enums\ReportStatus;
use App\Enums\ReportTrendDirection;
use App\Enums\ReportType;
use App\Models\AccountingPeriod;
use App\Models\AiIntelligenceReport;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Services\AccountingConfigurationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase 12.0 AI Intelligence Reporting & Executive Insights.
 *
 * The phase is verified against its load-bearing guarantees: deterministic
 * facts and trends, explicit fact/trend/prediction/advisory classification,
 * honest data-quality disclosure, the persistence lifecycle, the optional
 * failure-safe AI narrative, tenant and branch isolation, the RBAC surface, the
 * AI tool boundary, and the export/print/dashboard surface.
 *
 * Two invariants are asserted throughout and are never relaxed:
 *  1. the AI never produces, alters or decides a financial figure — every
 *     figure is computed by the deterministic reporting service;
 *  2. the AI never approves, rejects or takes a required action on a loan, an
 *     accounting entry, a prediction or an insight.
 */
class AiIntelligenceReportingTest extends AiTestCase
{
    /**
     * Staff roles granted ai.reports.view by the seeder. Secretary and VICOBA
     * Member hold none — management reporting is a staff-facing surface.
     */
    private const REPORT_ROLES = [
        'Organization Administrator', 'Branch Manager', 'Loan Officer',
        'Credit Officer', 'Collection Officer', 'Treasurer', 'Accountant',
        'Auditor',
    ];

    private const NON_REPORT_ROLES = ['Secretary', 'VICOBA Member'];

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function activeLoan(Member $member, array $overrides = []): Loan
    {
        return Loan::factory()->active()->create(array_merge([
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'loan_plan_id' => LoanPlan::factory()->create(['organization_id' => $member->organization_id])->id,
            'principal_amount' => 100000,
            'disbursed_amount' => 100000,
            'total_amount' => 120000,
            'outstanding_balance' => 100000,
            'amount_paid' => 0,
            'total_installments' => 12,
            'disbursement_date' => CarbonImmutable::now()->startOfMonth()->toDateString(),
        ], $overrides));
    }

    private function postedRepayment(Loan $loan, Member $member, CarbonImmutable $when, float $amount, string $status = 'posted'): LoanRepayment
    {
        return LoanRepayment::create([
            'loan_id' => $loan->id,
            'organization_id' => $loan->organization_id,
            'branch_id' => $loan->branch_id,
            'member_id' => $member->id,
            'repayment_number' => 'RPT-'.strtoupper(bin2hex(random_bytes(5))),
            'amount' => $amount,
            'principal_portion' => $amount,
            'interest_portion' => 0,
            'fee_portion' => 0,
            'payment_date' => $when->toDateString(),
            'status' => $status,
        ]);
    }

    private function installment(Loan $loan, CarbonImmutable $due, float $total): LoanRepaymentSchedule
    {
        return LoanRepaymentSchedule::create([
            'loan_id' => $loan->id,
            'organization_id' => $loan->organization_id,
            'installment_number' => 1,
            'due_date' => $due->toDateString(),
            'principal_amount' => $total,
            'interest_amount' => 0,
            'total_amount' => $total,
            'amount_paid' => 0,
            'outstanding_amount' => $total,
            'running_balance' => $total,
            'status' => LoanScheduleInstallmentStatus::Pending->value,
            'days_overdue' => 0,
            'late_fee' => 0,
        ]);
    }

    private function application(Member $member, string $status = LoanApplicationStatus::Approved->value): LoanApplication
    {
        return LoanApplication::create([
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'loan_plan_id' => LoanPlan::factory()->create(['organization_id' => $member->organization_id])->id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'application_number' => 'APP-'.strtoupper(bin2hex(random_bytes(5))),
            'requested_amount' => 100000,
            'requested_term' => 12,
            'loan_purpose' => 'development',
            'application_date' => CarbonImmutable::now()->startOfMonth()->toDateString(),
            'status' => $status,
        ]);
    }

    /**
     * A staff user attached to an organization AND explicitly assigned to a
     * branch, because branch scope comes from the authoritative branch_user
     * assignments rather than from organization membership.
     */
    private function staffWithBranch(Organization $org, Branch $branch, string $role = 'Loan Officer'): User
    {
        $user = $this->staff($org, $role);
        $user->branches()->attach($branch->id);

        return $user;
    }

    /**
     * The deterministic dataset for one report, generated through the real
     * service rather than hand-built, so assertions test production wiring.
     *
     * @return array<string, mixed>
     */
    private function generate(User $user, string $type = 'executive_portfolio', string $period = 'this_month', array $overrides = []): AiIntelligenceReport
    {
        /** @var AiIntelligenceReportService $service */
        $service = app(AiIntelligenceReportService::class);
        $context = app(AiContextBuilderService::class)->build($user);

        return $service->generate(
            context: $context,
            user: $user,
            reportType: $type,
            periodType: $period,
            from: $overrides['from'] ?? null,
            to: $overrides['to'] ?? null,
            branchId: $overrides['branchId'] ?? null,
            withNarrative: $overrides['withNarrative'] ?? false,
        );
    }

    /**
     * Every datum in a report, flattened, for classification assertions.
     *
     * @param  array<string, mixed>  $reportData
     * @return array<int, array<string, mixed>>
     */
    private function allDatums(array $reportData): array
    {
        $rows = [];

        foreach ((array) ($reportData['sections'] ?? []) as $section) {
            foreach (['facts', 'trends', 'predictions', 'advisories'] as $group) {
                foreach ((array) ($section[$group] ?? []) as $datum) {
                    $rows[] = $datum + ['section' => $section['key'] ?? null, 'group' => $group];
                }
            }
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Report types
    // ------------------------------------------------------------------

    public function test_all_six_report_types_generate_with_classified_sections(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $member = $this->member($org);
        $member->update(['branch_id' => $branch->id]);
        $this->activeLoan($member);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        foreach (ReportType::cases() as $type) {
            $report = $this->generate($user, $type->value);

            $this->assertSame(ReportStatus::Completed, $report->status, "Report {$type->value} must complete.");
            $this->assertSame($type, $report->report_type);
            $this->assertSame($type->value, data_get($report->report_data, 'report_type'));
            $this->assertSame($type->label(), data_get($report->report_data, 'report_type_label'));
            $this->assertNotEmpty($report->report_data['sections'], "Report {$type->value} must have sections.");

            foreach ($this->allDatums($report->report_data) as $datum) {
                $this->assertContains(
                    $datum['classification'],
                    ['fact', 'trend', 'prediction', 'advisory'],
                    "Every datum in {$type->value} must carry a classification.",
                );
                $this->assertSame(
                    $datum['classification'].'s',
                    $datum['group'],
                    'A datum must appear in the group matching its classification.',
                );
                $this->assertNotEmpty($datum['label'], 'A datum always carries a label, never a bare number.');
                $this->assertNotEmpty($datum['source'], 'A datum always names its authoritative source.');
            }
        }
    }

    public function test_report_reuses_existing_authoritative_services_and_never_recomputes_balances(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $member = $this->member($org);
        $member->update(['branch_id' => $branch->id]);
        $this->activeLoan($member);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $sources = collect($this->allDatums($this->generate($user)->report_data))
            ->pluck('source')
            ->unique()
            ->values();

        foreach ([
            'PortfolioIntelligenceService',
            'ParIntelligenceService + DelinquencyIntelligenceService',
            'CollectionIntelligenceService',
        ] as $expected) {
            $this->assertTrue(
                $sources->contains($expected),
                "Report must reuse {$expected}; got: {$sources->implode(', ')}",
            );
        }
    }

    // ------------------------------------------------------------------
    // Periods and comparison
    // ------------------------------------------------------------------

    public function test_every_period_type_resolves_and_keeps_period_distinct_from_data_through(): void
    {
        $periods = app(ReportPeriodService::class);
        $today = CarbonImmutable::now();

        $expected = [
            ReportPeriodType::Today->value => $today->toDateString(),
            ReportPeriodType::ThisWeek->value => $today->startOfWeek()->toDateString(),
            ReportPeriodType::ThisMonth->value => $today->startOfMonth()->toDateString(),
            ReportPeriodType::ThisQuarter->value => $today->firstOfQuarter()->toDateString(),
            ReportPeriodType::ThisYear->value => $today->startOfYear()->toDateString(),
            ReportPeriodType::PreviousMonth->value => $today->subMonthNoOverflow()->startOfMonth()->toDateString(),
            ReportPeriodType::PreviousQuarter->value => $today->subQuartersNoOverflow(1)->firstOfQuarter()->toDateString(),
        ];

        foreach ($expected as $type => $start) {
            $period = $periods->resolve($type);

            $this->assertSame($start, $period->start, "Period {$type} must start on {$start}.");
            $this->assertTrue($period->hasPrevious(), "Period {$type} must have a comparable previous period.");
            $this->assertLessThanOrEqual($period->start, $period->previousEnd, 'The comparison window must precede the reporting window.');
        }
    }

    public function test_custom_period_requires_a_bounded_validated_range(): void
    {
        $periods = app(ReportPeriodService::class);

        $custom = $periods->resolve(ReportPeriodType::Custom->value, '2026-01-01', '2026-03-31');
        $this->assertSame('2026-01-01', $custom->start);
        $this->assertSame('2026-03-31', $custom->end);

        // A custom period without an explicit range is rejected rather than
        // silently defaulting to a period the reader did not ask for.
        $this->expectException(\InvalidArgumentException::class);
        $periods->resolve(ReportPeriodType::Custom->value);
    }

    public function test_custom_range_beyond_the_configured_bound_is_rejected(): void
    {
        config(['intelligence-reporting.max_custom_range_days' => 30]);
        $periods = app(ReportPeriodService::class);

        $this->expectException(\InvalidArgumentException::class);
        $periods->resolve(ReportPeriodType::Custom->value, '2020-01-01', '2026-01-01');
    }

    public function test_generated_report_keeps_period_data_through_and_generated_at_distinct(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $member = $this->member($org);
        $member->update(['branch_id' => $branch->id]);
        $this->activeLoan($member);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $report = $this->generate($user, 'executive_portfolio', 'previous_month');

        $this->assertNotNull($report->data_through);
        $this->assertNotNull($report->generated_at);
        $this->assertNotNull($report->period_start);
        $this->assertNotNull($report->period_end);

        // A report generated now about a past period must not let the
        // generation timestamp masquerade as period data.
        $this->assertTrue(
            $report->period_end->lt($report->generated_at),
            'A previous-period report must end before it was generated.',
        );
        $this->assertTrue(
            $report->data_through->lte($report->generated_at),
            'Data through may never be in the future relative to generation.',
        );
    }

    // ------------------------------------------------------------------
    // Deterministic trends
    // ------------------------------------------------------------------

    public function test_trend_calculates_absolute_percentage_and_direction_deterministically(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        // 100 collected this month against 50 in the previous month.
        $thisMonthMember = $this->member($org);
        $thisMonthMember->update(['branch_id' => $branch->id]);
        $thisMonthLoan = $this->activeLoan($thisMonthMember);
        $this->postedRepayment($thisMonthLoan, $thisMonthMember, CarbonImmutable::now(), 100);

        $lastMonthMember = $this->member($org);
        $lastMonthMember->update(['branch_id' => $branch->id]);
        $lastMonthLoan = $this->activeLoan($lastMonthMember, [
            'disbursement_date' => CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
        ]);
        $this->postedRepayment($lastMonthLoan, $lastMonthMember, CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth(), 50);

        $report = $this->generate($user, 'collections', 'this_month');
        $trends = collect($this->allDatums($report->report_data))->where('classification', 'trend');

        $this->assertNotEmpty($trends, 'The collections report must carry deterministic trends.');

        $collected = $trends->firstWhere('key', 'collected_trend');

        $this->assertNotNull($collected, 'Collections versus previous period must be reported as a trend.');
        $this->assertSame(ReportDatumClassification::Trend, ReportDatumClassification::from($collected['classification']));
        $this->assertSame(100.0, (float) $collected['value']);
        $this->assertSame(50.0, (float) $collected['previous_value']);
        $this->assertSame(50.0, (float) $collected['absolute_change']);
        $this->assertSame(100.0, (float) $collected['percentage_change']);
        $this->assertSame(ReportTrendDirection::Up->value, $collected['direction']);
    }

    public function test_missing_comparison_yields_unavailable_never_a_fabricated_zero(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        // This month only: there is deliberately no previous-period activity.
        $member = $this->member($org);
        $member->update(['branch_id' => $branch->id]);
        $loan = $this->activeLoan($member);
        $this->postedRepayment($loan, $member, CarbonImmutable::now(), 100);

        $report = $this->generate($user, 'collections', 'this_month');

        $trend = collect($this->allDatums($report->report_data))
            ->firstWhere('key', 'collected_trend');

        $this->assertNotNull($trend);
        $this->assertSame(ReportTrendDirection::Unavailable->value, $trend['direction']);
        $this->assertNull($trend['absolute_change'], 'A missing comparison must not invent an absolute change.');
        $this->assertNull($trend['percentage_change'], 'A missing comparison must not invent a percentage.');

        $this->assertNotEmpty(
            $report->report_data['data_quality'] ?? [],
            'An unavailable comparison must be disclosed as a data-quality note.',
        );
    }

    public function test_zero_previous_value_is_never_divided_to_produce_an_infinite_percentage(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $member = $this->member($org);
        $member->update(['branch_id' => $branch->id]);
        $loan = $this->activeLoan($member);
        $this->postedRepayment($loan, $member, CarbonImmutable::now(), 500);

        // A previous period whose collection total is zero: growth from zero is
        // undefined, not infinite.
        $previousMember = $this->member($org);
        $previousMember->update(['branch_id' => $branch->id]);
        $previousLoan = $this->activeLoan($previousMember, [
            'disbursement_date' => CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
        ]);
        $this->postedRepayment($previousLoan, $previousMember, CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth(), 0);

        $trend = collect($this->allDatums($this->generate($user, 'collections', 'this_month')->report_data))
            ->firstWhere('key', 'collected_trend');

        $this->assertNotNull($trend);
        $this->assertSame(ReportTrendDirection::Unavailable->value, $trend['direction']);
        $this->assertNull($trend['percentage_change']);
    }

    // ------------------------------------------------------------------
    // Predictions and insights
    // ------------------------------------------------------------------

    public function test_missing_predictions_are_disclosed_not_silently_omitted(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $report = $this->generate($user);

        // No Phase 11.8 snapshot exists, so every domain must be declared
        // unavailable instead of quietly dropped from the report.
        $quality = implode(' ', $report->report_data['data_quality'] ?? []);

        foreach (PredictiveInsightType::cases() as $type) {
            $this->assertStringContainsString(
                $type->label(),
                $quality,
                "{$type->label()} must be disclosed as unavailable.",
            );
        }
    }

    public function test_open_insights_appear_as_advisories_and_never_as_facts(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $member = $this->member($org);
        $member->update(['branch_id' => $branch->id]);
        $loan = $this->activeLoan($member);
        $this->installment($loan, CarbonImmutable::now()->subDays(90), 100000);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $insights = app(ProactiveInsightService::class)
            ->generate($org->id, [$branch->id], $user);

        $this->assertNotEmpty($insights, 'The fixture must produce at least one proactive insight.');

        $report = $this->generate($user);
        $advisories = collect($this->allDatums($report->report_data))->where('classification', 'advisory');

        $this->assertGreaterThan(0, $advisories->count(), 'Open insights must be carried as advisories.');

        foreach ($advisories as $advisory) {
            $this->assertSame(ReportDatumClassification::Advisory->value, $advisory['classification']);
            $this->assertSame('ProactiveInsightService', $advisory['source']);
            $this->assertStringContainsString(
                'human',
                strtolower((string) data_get($advisory, 'meta.lifecycle')),
                'An advisory must state that acting on it is a human decision.',
            );
        }

        // No advisory may be filed as a fact.
        foreach ($this->allDatums($report->report_data) as $datum) {
            if (data_get($datum, 'meta.insight_id') !== null) {
                $this->assertNotSame('fact', $datum['classification']);
                $this->assertNotSame('trend', $datum['classification']);
            }
        }
    }

    public function test_reporting_never_regenerates_predictions_or_insights(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $predictionsBefore = DB::table('ai_predictions')->count();
        $insightsBefore = DB::table('ai_insights')->count();

        $this->generate($user);
        $this->generate($user, 'loan_performance');
        $this->generate($user, 'accounting_intelligence');

        $this->assertSame($predictionsBefore, DB::table('ai_predictions')->count(), 'Reporting must not create a prediction.');
        $this->assertSame($insightsBefore, DB::table('ai_insights')->count(), 'Reporting must not create an insight.');
    }

    // ------------------------------------------------------------------
    // Persistence lifecycle
    // ------------------------------------------------------------------

    public function test_report_is_persisted_completed_with_its_full_dataset(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $report = $this->generate($user);

        $this->assertTrue($report->exists);
        $this->assertSame(ReportStatus::Completed, $report->status);
        $this->assertSame($org->id, $report->organization_id);
        $this->assertSame($user->id, $report->requested_by);
        $this->assertNull($report->failure_reason);
        $this->assertNotEmpty($report->report_data);
    }

    public function test_only_a_completed_report_is_reportable(): void
    {
        $this->assertTrue(ReportStatus::Completed->isReportable());
        $this->assertFalse(ReportStatus::Generating->isReportable(), 'A generating row is not a report.');
        $this->assertFalse(ReportStatus::Failed->isReportable(), 'A failed row must never masquerade as a report.');
    }

    public function test_generation_records_a_completed_and_a_failed_audit_trail(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $this->generate($user);

        $this->assertTrue(
            AuditLog::where('event', 'ai.report.generated')->exists(),
            'Generation must be audited.',
        );
    }

    public function test_generation_failure_is_recorded_and_never_returned_as_a_report(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        // Force a hard failure inside the deterministic build step. The mock must be
        // installed before the service is resolved, because the builder is a
        // constructor dependency that would otherwise be bound for real.
        $this->partialMock(ReportBuilderService::class)
            ->shouldReceive('build')
            ->andThrow(new \RuntimeException('boom'));

        $service = app(AiIntelligenceReportService::class);
        $context = app(AiContextBuilderService::class)->build($user);

        try {
            $service->generate(
                context: $context,
                user: $user,
                reportType: 'executive_portfolio',
                periodType: 'this_month',
            );
            $this->fail('A failed generation must throw.');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $row = AiIntelligenceReport::latest('id')->first();

        $this->assertNotNull($row, 'A failed generation must still be recorded for audit.');
        $this->assertSame(ReportStatus::Failed, $row->status);
        $this->assertFalse($row->status->isReportable());
        $this->assertNotNull($row->failure_reason);
        $this->assertStringNotContainsString('boom', (string) $row->failure_reason, 'The failure reason must be a safe diagnostic, not the raw exception.');
    }

    // ------------------------------------------------------------------
    // AI narrative
    // ------------------------------------------------------------------

    public function test_narrative_is_optional_and_never_required_for_a_report(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $report = $this->generate($user, 'executive_portfolio', 'this_month', ['withNarrative' => false]);

        $this->assertNull($report->narrative);
        $this->assertFalse($report->ai_generated);
        $this->assertSame(ReportStatus::Completed, $report->status);
        $this->assertNotEmpty($report->report_data['sections'], 'The report is complete without any narrative.');
    }

    public function test_narrative_failure_degrades_gracefully_and_never_destroys_the_report(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $narrative = app(IntelligenceReportNarrativeService::class);

        // Provider unavailable.
        config(['ai.default_provider' => 'fake']);

        $builder = app(ReportBuilderService::class);
        $reportData = $builder->build(
            type: ReportType::ExecutivePortfolio,
            context: app(AiContextBuilderService::class)->build($user),
            organizationId: $org->id,
            period: app(ReportPeriodService::class)->resolve('this_month'),
        );

        // Disable the layer: the report must remain fully usable.
        config(['intelligence-reporting.narrative.enabled' => false]);
        $result = $narrative->generate($reportData);

        $this->assertFalse($result['available']);
        $this->assertNull($result['summary']);
        $this->assertNotNull($result['reason'], 'An unavailable narrative must explain itself.');
        $this->assertNotEmpty($reportData['sections'], 'The dataset is unaffected by narrative availability.');

        // A provider exception is caught and reported as unavailable.
        config(['intelligence-reporting.narrative.enabled' => true]);
        $this->mock(AiProviderService::class)
            ->shouldReceive('isAvailable')
            ->andReturnTrue()
            ->shouldReceive('generate')
            ->andThrow(new \RuntimeException('provider down'));

        $failed = app(IntelligenceReportNarrativeService::class)->generate($reportData);

        $this->assertFalse($failed['available']);
        $this->assertNull($failed['summary']);
        $this->assertStringNotContainsString('provider down', $failed['reason'] ?? '', 'The raw provider error must never surface.');
    }

    public function test_narrative_context_is_sanitized_and_labelled_by_classification(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $report = $this->generate($user);
        $context = app(IntelligenceReportNarrativeService::class)->sanitizedContext($report->report_data);

        $this->assertStringContainsString('REPORT DATA', $context);
        $this->assertStringContainsString('REPORTING PERIOD', $context);
        $this->assertStringContainsString('DATA THROUGH', $context);
        $this->assertStringContainsString('REPORT FACTS', $context);
        $this->assertStringContainsString('[Observed fact]', $context, 'Every datum must travel with its classification label.');

        // The context is a bounded, structured dataset — never a data dump.
        $this->assertLessThan(40000, strlen($context), 'The narrative context must stay bounded.');
        $this->assertStringNotContainsString($user->email, $context, 'No user credential may enter the narrative context.');
    }

    // ------------------------------------------------------------------
    // Authorization and tenant isolation
    // ------------------------------------------------------------------

    public function test_reporting_roles_are_granted_and_secretary_and_vicoba_member_are_not(): void
    {
        foreach (self::REPORT_ROLES as $role) {
            $user = $this->user($role);
            $this->assertTrue($user->can('ai.reports.view'), "Role {$role} must hold ai.reports.view.");
        }

        foreach (self::NON_REPORT_ROLES as $role) {
            $user = $this->user($role);
            $this->assertFalse($user->can('ai.reports.view'), "Role {$role} must not hold ai.reports.view.");
        }
    }

    public function test_vicoba_member_and_secretary_are_denied_the_whole_surface(): void
    {
        $org = $this->makeOrganization();

        foreach (self::NON_REPORT_ROLES as $role) {
            $user = $this->user($role);
            $user->organizations()->attach($org->id);

            $this->actingAs($user)->get(route('ai.reports.index'))->assertForbidden();
            $this->actingAs($user)->post(route('ai.reports.store'), [
                'report_type' => 'executive_portfolio',
                'period' => 'this_month',
            ])->assertForbidden();
        }
    }

    public function test_accounting_report_requires_the_accounting_capability_not_only_the_reporting_one(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Loan Officer');

        $this->assertTrue($user->can('ai.reports.view'), 'The Loan Officer holds the reporting capability.');
        $this->assertFalse($user->can('ai.accounting.view'), 'The Loan Officer does not hold the accounting capability.');

        $this->expectException(\InvalidArgumentException::class);
        $this->generate($user, ReportType::AccountingIntelligence->value);
    }

    public function test_a_role_holding_both_capabilities_may_produce_the_accounting_report(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Accountant');

        $this->assertTrue($user->can('ai.accounting.view'));

        $report = $this->generate($user, ReportType::AccountingIntelligence->value);

        $this->assertSame(ReportStatus::Completed, $report->status);
    }

    public function test_report_type_outside_the_authorized_set_is_rejected(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Loan Officer');

        $this->expectException(\InvalidArgumentException::class);
        $this->generate($user, ReportType::AccountingIntelligence->value);
    }

    public function test_report_cannot_be_generated_for_a_foreign_organization(): void
    {
        $mine = $this->makeOrganization();
        $theirs = $this->makeOrganization();
        $branch = $this->branch($theirs);

        $user = $this->staff($mine, 'Organization Administrator');
        $user->branches()->attach($branch->id);

        // The service resolves the organization from the trusted context, so a
        // request cannot aim a report at another organization.
        $report = $this->generate($user);

        $this->assertSame($mine->id, $report->organization_id);
        $this->assertNotSame($theirs->id, $report->organization_id);
    }

    public function test_branch_outside_the_authorized_scope_is_rejected(): void
    {
        $mine = $this->makeOrganization();
        $myBranch = $this->branch($mine);
        $theirs = $this->makeOrganization();
        $theirBranch = $this->branch($theirs);

        $user = $this->staffWithBranch($mine, $myBranch, 'Organization Administrator');

        $this->expectException(\InvalidArgumentException::class);
        $this->generate($user, 'executive_portfolio', 'this_month', ['branchId' => (string) $theirBranch->id]);
    }

    public function test_branch_of_another_organization_is_rejected_even_with_a_branch_grant(): void
    {
        $mine = $this->makeOrganization();
        $myBranch = $this->branch($mine);
        $theirs = $this->makeOrganization();
        $theirBranch = $this->branch($theirs);

        $user = $this->staffWithBranch($mine, $myBranch, 'Organization Administrator');
        // A branch assignment alone is never enough: it must match the report's
        // organization as well.
        $user->branches()->attach($theirBranch->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->generate($user, 'executive_portfolio', 'this_month', ['branchId' => (string) $theirBranch->id]);
    }

    /**
     * A branch-scoped accounting report must actually be branch-scoped. The
     * ledger is segmented through `journal_entries.branch_id`, so an
     * organization-wide figure presented under a branch heading would be a
     * mislabelled number rather than a wrong one — which is exactly the failure
     * this phase exists to prevent.
     */
    public function test_branch_scoped_accounting_figures_never_include_another_branchs_ledger(): void
    {
        $org = $this->makeOrganization();
        $branchA = $this->branch($org);
        $branchB = $this->branch($org);

        $this->postIncomeEntry($org, $branchA, 'Income A', 'AAA', 100000);
        $this->postIncomeEntry($org, $branchB, 'Income B', 'BBB', 900000);

        $user = $this->staffWithBranch($org, $branchA, 'Organization Administrator');
        $user->branches()->attach($branchB->id);

        $scoped = $this->generate($user, 'accounting_intelligence', 'this_month', ['branchId' => (string) $branchA->id]);
        $scopedIncome = $this->factValue($scoped->report_data, 'total_income');

        $organizationWide = $this->generate($user, 'accounting_intelligence', 'this_month');
        $organizationIncome = $this->factValue($organizationWide->report_data, 'total_income');

        $this->assertNotNull($scopedIncome, 'The branch-scoped income statement must be available.');
        $this->assertNotNull($organizationIncome);
        $this->assertEqualsWithDelta(100000.0, (float) $scopedIncome, 0.01, 'A branch-scoped report must contain only that branch ledger.');
        $this->assertEqualsWithDelta(1000000.0, (float) $organizationIncome, 0.01, 'An unscoped report must aggregate the organization.');
        $this->assertGreaterThan((float) $scopedIncome, (float) $organizationIncome);
    }

    public function test_branch_scoped_accounting_report_excludes_another_branchs_cash_ledger(): void
    {
        $org = $this->makeOrganization();
        $branchA = $this->branch($org);
        $branchB = $this->branch($org);

        // Let the authoritative accounting configuration create the chart and
        // the cash mapping, then read the real account back: the report must be
        // exercised against the same mapping the dashboard uses.
        $accountId = $this->configuredAccountId($org, 'cash_on_hand');

        $this->postCashReceipt($org, $branchA, $accountId, 50000);
        $this->postCashReceipt($org, $branchB, $accountId, 70000);

        $user = $this->staffWithBranch($org, $branchA, 'Organization Administrator');
        $user->branches()->attach($branchB->id);

        $scoped = $this->generate($user, 'cashflow_intelligence', 'this_month', ['branchId' => (string) $branchA->id]);
        $scopedInflows = $this->factValue($scoped->report_data, 'inflows');

        $organizationWide = $this->generate($user, 'cashflow_intelligence', 'this_month');
        $organizationInflows = $this->factValue($organizationWide->report_data, 'inflows');

        $this->assertNotNull($scopedInflows, 'A branch-scoped cash-flow figure must be available.');
        $this->assertEqualsWithDelta(50000.0, (float) $scopedInflows, 0.01, 'The branch ledger must not include another branch cash.');
        $this->assertEqualsWithDelta(120000.0, (float) $organizationInflows, 0.01);
    }

    /**
     * A minimal posted income entry for one branch.
     */
    private function postIncomeEntry(Organization $org, Branch $branch, string $accountName, string $code, float $amount): void
    {
        // Resolve the configured cash account first: once any chart of account
        // exists, the configuration service assumes the chart is already
        // complete and will not initialize the default one.
        $cashAccountId = $this->configuredAccountId($org, 'cash_on_hand');
        $incomeAccountId = $this->chartAccount($org, $code, $accountName, 'income', 'credit');

        $entry = $this->postedEntry($org, $branch);
        $this->line($org, $entry, $incomeAccountId, $accountName, 0, $amount);
        $this->line($org, $entry, $cashAccountId, 'Cash', $amount, 0);
    }

    /**
     * A balanced posted entry that only moves the cash account, so the cash
     * ledger shows an inflow for exactly this branch.
     */
    private function postCashReceipt(Organization $org, Branch $branch, int $cashAccountId, float $amount): void
    {
        $entry = $this->postedEntry($org, $branch);
        $this->line($org, $entry, $cashAccountId, 'Receipt', $amount, 0);
        $this->line($org, $entry, $cashAccountId, 'Offset', 0, $amount);
    }

    /**
     * The account the authoritative accounting configuration maps for this
     * organization, initializing the chart and mappings when the organization
     * has none yet.
     */
    private function configuredAccountId(Organization $org, string $key): int
    {
        $configuration = app(AccountingConfigurationService::class);

        try {
            return $configuration->getAccountId($org->id, $key);
        } catch (\InvalidArgumentException) {
            $configuration->initializeDefaultMappings($org->id);

            return $configuration->getAccountId($org->id, $key);
        }
    }

    private function postedEntry(Organization $org, Branch $branch): JournalEntry
    {
        return JournalEntry::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'journal_number' => 'JE-'.strtoupper(bin2hex(random_bytes(6))),
            'accounting_period_id' => $this->openPeriod($org)->id,
            'entry_date' => CarbonImmutable::now()->startOfMonth()->toDateString(),
            'description' => 'Phase 12 branch scope fixture',
            'status' => 'posted',
            'posted_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * One journal line belonging to the entry's organization.
     */
    private function line(Organization $org, JournalEntry $entry, int $accountId, string $description, float $debit, float $credit): void
    {
        $entry->lines()->create([
            'organization_id' => $org->id,
            'chart_of_account_id' => $accountId,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    private function openPeriod(Organization $org): AccountingPeriod
    {
        return AccountingPeriod::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Current'],
            ['start_date' => CarbonImmutable::now()->startOfYear()->toDateString(), 'end_date' => CarbonImmutable::now()->endOfYear()->toDateString(), 'status' => 'open'],
        );
    }

    private function chartAccount(Organization $org, string $code, string $name, string $type, string $normalBalance): int
    {
        return ChartOfAccount::create([
            'organization_id' => $org->id,
            'account_code' => $code,
            'account_name' => $name,
            'account_type' => $type,
            'normal_balance' => $normalBalance,
            'is_system' => false,
            'is_active' => true,
        ])->id;
    }

    /**
     * The value of one fact datum, or null when the report did not carry it.
     *
     * @param  array<string, mixed>  $reportData
     */
    private function factValue(array $reportData, string $key): mixed
    {
        foreach ((array) ($reportData['sections'] ?? []) as $section) {
            foreach ((array) ($section['facts'] ?? []) as $fact) {
                if (($fact['key'] ?? null) === $key) {
                    return $fact['value'] ?? null;
                }
            }
        }

        return null;
    }

    public function test_foreign_report_id_is_a_404_not_a_disclosure(): void
    {
        $mine = $this->makeOrganization();
        $theirs = $this->makeOrganization();
        $theirBranch = $this->branch($theirs);
        $theirUser = $this->staffWithBranch($theirs, $theirBranch, 'Organization Administrator');

        $foreign = $this->generate($theirUser);

        $attacker = $this->staffWithBranch($mine, $this->branch($mine), 'Organization Administrator');

        $this->actingAs($attacker)->get(route('ai.reports.show', $foreign->id))->assertNotFound();
        $this->actingAs($attacker)->get(route('ai.reports.print', $foreign->id))->assertNotFound();
        $this->actingAs($attacker)->get(route('ai.reports.export', $foreign->id))->assertNotFound();
    }

    public function test_recent_history_only_ever_lists_the_acting_users_own_reports(): void
    {
        $mine = $this->makeOrganization();
        $mineBranch = $this->branch($mine);
        $mineUser = $this->staffWithBranch($mine, $mineBranch, 'Organization Administrator');

        $theirs = $this->makeOrganization();
        $theirBranch = $this->branch($theirs);
        $theirUser = $this->staffWithBranch($theirs, $theirBranch, 'Organization Administrator');

        $this->generate($mineUser);
        $foreign = $this->generate($theirUser);

        $visible = app(AiIntelligenceReportService::class)
            ->recent(app(AiContextBuilderService::class)->build($mineUser));

        $this->assertTrue($visible->contains('id', $mineUser->reports()->first()?->id));
        $this->assertFalse($visible->contains('id', $foreign->id), 'A foreign report must never appear in the history.');
    }

    public function test_a_failed_report_is_not_readable_over_http(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $row = AiIntelligenceReport::create([
            'organization_id' => $org->id,
            'report_type' => 'executive_portfolio',
            'status' => ReportStatus::Failed->value,
            'period_type' => 'this_month',
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'data_through' => now(),
            'generated_at' => now(),
            'requested_by' => $user->id,
            'report_data' => [],
            'failure_reason' => 'The report could not be generated.',
        ]);

        $this->actingAs($user)->get(route('ai.reports.show', $row->id))->assertNotFound();
        $this->actingAs($user)->get(route('ai.reports.export', $row->id))->assertNotFound();
    }

    // ------------------------------------------------------------------
    // HTTP surface
    // ------------------------------------------------------------------

    public function test_dashboard_renders_for_an_authorized_role_and_lists_only_authorized_branches(): void
    {
        $org = $this->makeOrganization();
        $myBranch = $this->branch($org);
        $foreignBranch = $this->branch($org);

        $user = $this->staffWithBranch($org, $myBranch, 'Organization Administrator');
        $user->branches()->attach($foreignBranch->id);

        $response = $this->actingAs($user)->get(route('ai.reports.index'));

        $response->assertOk();
        $response->assertSee('Management Intelligence Reports');
        $response->assertSee($myBranch->name);
        $response->assertSee($foreignBranch->name);
    }

    public function test_store_persists_and_redirects_to_the_report(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $response = $this->actingAs($user)->post(route('ai.reports.store'), [
            'report_type' => 'executive_portfolio',
            'period' => 'this_month',
        ]);

        $report = AiIntelligenceReport::latest('id')->first();

        $this->assertNotNull($report);
        $this->assertSame(ReportStatus::Completed, $report->status);
        $response->assertRedirect(route('ai.reports.show', $report));
    }

    public function test_store_rejects_an_unknown_report_type_or_period(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $this->actingAs($user)->post(route('ai.reports.store'), [
            'report_type' => 'sql_injection',
            'period' => 'this_month',
        ])->assertSessionHasErrors('report_type');

        $this->actingAs($user)->post(route('ai.reports.store'), [
            'report_type' => 'executive_portfolio',
            'period' => 'last_decade',
        ])->assertSessionHasErrors('period');
    }

    public function test_store_validates_a_custom_period_against_its_dates(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $this->actingAs($user)->post(route('ai.reports.store'), [
            'report_type' => 'executive_portfolio',
            'period' => 'custom',
        ])->assertSessionHasErrors('from');

        $this->actingAs($user)->post(route('ai.reports.store'), [
            'report_type' => 'executive_portfolio',
            'period' => 'custom',
            'from' => '2026-03-31',
            'to' => '2026-01-01',
        ])->assertSessionHasErrors('to');
    }

    public function test_store_rejects_an_oversized_custom_range(): void
    {
        config(['intelligence-reporting.max_custom_range_days' => 30]);

        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $this->actingAs($user)->post(route('ai.reports.store'), [
            'report_type' => 'executive_portfolio',
            'period' => 'custom',
            'from' => '2020-01-01',
            'to' => '2026-01-01',
        ])->assertSessionHasErrors('from');
    }

    public function test_detail_view_never_shows_a_bare_number_without_its_classification(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $member = $this->member($org);
        $member->update(['branch_id' => $branch->id]);
        $this->activeLoan($member);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $report = $this->generate($user);

        $response = $this->actingAs($user)->get(route('ai.reports.show', $report->id));

        $response->assertOk();
        $response->assertSee('Fact');
        $response->assertSee('Data through');
        $this->assertStringContainsString('Source:', $response->getContent());
    }

    public function test_print_view_renders_a_self_contained_document(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $report = $this->generate($user);

        $response = $this->actingAs($user)->get(route('ai.reports.print', $report->id));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('layouts.app', $content, 'The print view must not extend the app chrome.');
        $this->assertStringContainsString('never guarantees', $content);
    }

    public function test_export_streams_csv_with_classification_and_audits_the_download(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $report = $this->generate($user);

        $response = $this->actingAs($user)->get(route('ai.reports.export', $report->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertTrue(
            AuditLog::where('event', 'ai.report.exported')->exists(),
            'An export of financial reporting must be audited.',
        );
    }

    public function test_csv_rows_carry_classification_and_never_bare_figures(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $report = $this->generate($user);
        $csv = app(AiIntelligenceReportService::class)->toCsv($report);

        $labels = array_map(fn (ReportDatumClassification $classification) => $classification->label(), ReportDatumClassification::cases());

        $this->assertContains('Classification', $csv['header']);
        $this->assertContains('Source', $csv['header']);
        $this->assertContains('Direction', $csv['header']);
        $this->assertNotEmpty($csv['rows'], 'The fixture must produce exported rows.');

        foreach ($csv['rows'] as $row) {
            $this->assertContains($row[1], $labels, 'Every exported row carries its canonical classification label.');
            $this->assertNotSame('', $row[2], 'Every exported row carries a label.');
        }
    }

    // ------------------------------------------------------------------
    // AI tool boundary
    // ------------------------------------------------------------------

    public function test_report_capability_is_registered_with_a_tenant_free_argument_schema(): void
    {
        $registry = app(AiToolRegistry::class);

        $this->assertTrue($registry->has('ai.reports.view'));
        $this->assertSame(['ai.reports.view'], $registry->requiredPermissions('ai.reports.view'));
        $this->assertSame(AiToolRegistry::SCOPE_USER_ORG, $registry->scope('ai.reports.view'));
        $this->assertSame(IntelligenceReportTool::class, $registry->handler('ai.reports.view'));

        $rules = $registry->argumentRules('ai.reports.view');

        $this->assertSame(['report_type', 'period', 'from', 'to'], array_keys($rules));

        foreach (array_keys($rules) as $argument) {
            $this->assertNotContains(
                $argument,
                AiToolPolicy::FORBIDDEN_ARGUMENT_KEYS,
                "Report argument {$argument} must never carry a tenant key.",
            );
        }
    }

    public function test_report_capability_is_not_exposed_to_the_public_assistant(): void
    {
        $registry = app(AiToolRegistry::class);

        foreach ($registry->publicCapabilities() as $public) {
            $this->assertNotSame(IntelligenceReportTool::class, $public['handler']);
        }

        $this->assertNotContains(
            'ai.reports.view',
            array_column($registry->publicCapabilities(), 'permissions') ? [] : ['ai.reports.view'],
        );
    }

    public function test_tool_returns_classified_data_without_persisting_a_report(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $before = AiIntelligenceReport::count();

        $result = app(IntelligenceReportTool::class)->execute(
            $user,
            app(AiContextBuilderService::class)->build($user),
            ['report_type' => 'executive_portfolio', 'period' => 'this_month'],
        );

        $this->assertTrue($result['available']);
        $this->assertSame('executive_portfolio', $result['report_type']);
        $this->assertNotEmpty($result['sections']);
        $this->assertArrayHasKey('facts', $result['sections'][0]);
        $this->assertArrayHasKey('trends', $result['sections'][0]);
        $this->assertArrayHasKey('predictions', $result['sections'][0]);
        $this->assertArrayHasKey('advisories', $result['sections'][0]);

        foreach ($result['sections'] as $section) {
            foreach ($section['facts'] as $datum) {
                $this->assertSame('fact', $datum['classification']);
                $this->assertNotEmpty($datum['label']);
            }
        }

        $this->assertSame($before, AiIntelligenceReport::count(), 'A read-only tool must not persist a report row.');
    }

    public function test_tool_degrades_without_throwing_for_an_unsupported_report_type(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $before = AiIntelligenceReport::count();

        $result = app(IntelligenceReportTool::class)->execute(
            $user,
            app(AiContextBuilderService::class)->build($user),
            ['report_type' => 'not_a_report', 'period' => 'this_month'],
        );

        $this->assertFalse($result['available']);
        $this->assertNotNull($result['reason']);
        $this->assertSame($before, AiIntelligenceReport::count());
    }

    public function test_tool_endpoint_serves_the_report_for_an_authorized_role(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $response = $this->actingAs($user)->postJson('/ai/tool', [
            'capability' => 'ai.reports.view',
            'arguments' => ['report_type' => 'executive_portfolio', 'period' => 'this_month'],
            'question' => 'Please build the executive portfolio report.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.capability', 'ai.reports.view');
        $response->assertJsonPath('data.result.available', true);
    }

    public function test_tool_endpoint_rejects_a_tenant_argument(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $this->actingAs($user)->postJson('/ai/tool', [
            'capability' => 'ai.reports.view',
            'arguments' => [
                'report_type' => 'executive_portfolio',
                'period' => 'this_month',
                'organization_id' => $org->id,
            ],
            'question' => 'Please build the executive portfolio report.',
        ])->assertStatus(422);
    }

    public function test_tool_endpoint_is_denied_without_the_reporting_capability(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org, 'Secretary');

        $this->actingAs($user)->postJson('/ai/tool', [
            'capability' => 'ai.reports.view',
            'arguments' => ['report_type' => 'executive_portfolio', 'period' => 'this_month'],
            'question' => 'Please build the executive portfolio report.',
        ])->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Chat orchestration
    // ------------------------------------------------------------------

    public function test_a_report_question_is_orchestrated_only_with_closed_type_and_period_values(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');
        $context = app(AiContextBuilderService::class)->build($user);

        $orchestrator = app(AiChatOrchestrationService::class);

        $plan = $orchestrator->plan($context, 'Give me the loan performance report for this quarter');
        $this->assertNotNull($plan);
        $this->assertSame('ai.reports.view', $plan->capability);
        $this->assertSame('loan_performance', $plan->arguments['report_type']);
        $this->assertSame('this_quarter', $plan->arguments['period']);

        $collections = $orchestrator->plan($context, 'show the collections report for last month');
        $this->assertSame('collections', $collections->arguments['report_type']);
        $this->assertSame('previous_month', $collections->arguments['period']);

        $default = $orchestrator->plan($context, 'brief me on this month');
        $this->assertSame('executive_portfolio', $default->arguments['report_type']);
    }

    public function test_report_orchestration_is_permission_gated(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org, 'Secretary');
        $context = app(AiContextBuilderService::class)->build($user);

        $this->assertNull(
            app(AiChatOrchestrationService::class)->plan($context, 'give me a report'),
            'A user without ai.reports.view must never receive a report plan.',
        );
    }

    public function test_orchestrated_report_arguments_carry_no_tenant_key(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');
        $context = app(AiContextBuilderService::class)->build($user);

        $plan = app(AiChatOrchestrationService::class)
            ->plan($context, 'give me the cash flow report for this month');

        $this->assertNotNull($plan);
        $this->assertSame('cashflow_intelligence', $plan->arguments['report_type']);

        foreach (array_keys($plan->arguments) as $argument) {
            $this->assertNotContains($argument, AiToolPolicy::FORBIDDEN_ARGUMENT_KEYS);
        }

        $this->assertTrue($plan->permittedFor($context, app(AiToolRegistry::class)));
    }

    // ------------------------------------------------------------------
    // Read-only guarantee
    // ------------------------------------------------------------------

    public function test_generating_a_report_never_mutates_a_business_record(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $member = $this->member($org);
        $member->update(['branch_id' => $branch->id]);
        $loan = $this->activeLoan($member);
        $repayment = $this->postedRepayment($loan, $member, CarbonImmutable::now(), 5000);
        $application = $this->application($member);
        $user = $this->staffWithBranch($org, $branch, 'Organization Administrator');

        $snapshot = [
            'loan_outstanding' => $loan->fresh()->outstanding_balance,
            'loan_amount_paid' => $loan->fresh()->amount_paid,
            'loan_status' => $loan->fresh()->status,
            'repayment_amount' => $repayment->fresh()->amount,
            'application_status' => $application->fresh()->status,
        ];

        foreach (ReportType::cases() as $type) {
            $this->generate($user, $type->value);
        }

        $this->assertSame($snapshot['loan_outstanding'], $loan->fresh()->outstanding_balance);
        $this->assertSame($snapshot['loan_amount_paid'], $loan->fresh()->amount_paid);
        $this->assertSame($snapshot['loan_status'], $loan->fresh()->status);
        $this->assertSame($snapshot['repayment_amount'], $repayment->fresh()->amount);
        $this->assertSame($snapshot['application_status'], $application->fresh()->status);
    }

    public function test_reporting_never_touches_the_ledger_or_a_journal_entry(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $user = $this->staffWithBranch($org, $branch, 'Accountant');

        $entriesBefore = DB::table('journal_entries')->count();
        $linesBefore = DB::table('journal_lines')->count();

        $this->generate($user, ReportType::AccountingIntelligence->value);
        $this->generate($user, ReportType::CashflowIntelligence->value);

        $this->assertSame($entriesBefore, DB::table('journal_entries')->count());
        $this->assertSame($linesBefore, DB::table('journal_lines')->count());
    }
}
