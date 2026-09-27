<?php

namespace Tests\Feature\AI;

use App\AI\PredictiveIntelligence\Services\PredictiveIntelligenceService;
use App\AI\Services\AiContextBuilderService;
use App\AI\Services\AiToolRegistry;
use App\AI\Tools\PredictiveInsightTool;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\PredictiveInsightStatus;
use App\Enums\PredictiveInsightType;
use App\Models\AiPrediction;
use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanPlan;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use Carbon\CarbonImmutable;

/**
 * Phase 11.8 Predictive Intelligence: the deterministic statistical outlooks,
 * their single read-only capability, the role grant matrix, idempotent and
 * audited snapshot persistence, tenant isolation, the overtime/look-ahead
 * guards, and the public-surface exclusion.
 *
 * Every assertion is about advisory read-only behavior: no prediction ever
 * changes a financial record, no future-dated or in-progress period ever
 * leaks into a series, member-level detail is never produced, and the public
 * landing-page assistant can never reach the ownership surface.
 */
class AiPredictiveIntelligenceTest extends AiTestCase
{
    /**
     * Staff roles granted ai.predictive.view by the seeder; Secretary and
     * VICOBA Member hold none (predictions are staff-facing aggregate signal).
     */
    private const PREDICTIVE_ROLES = [
        'Organization Administrator', 'Branch Manager', 'Loan Officer',
        'Credit Officer', 'Collection Officer', 'Treasurer', 'Accountant',
        'Auditor',
    ];

    // ------------------------------------------------------------------
    // Fixture helpers
    // ------------------------------------------------------------------

    private function activeLoan(Member $member, array $overrides = []): Loan
    {
        return Loan::factory()->active()->create(array_merge([
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'loan_plan_id' => LoanPlan::factory()->create(['organization_id' => $member->organization_id])->id,
            'principal_amount' => 900000,
            'disbursed_amount' => 900000,
            'total_amount' => 1080000,
            'outstanding_balance' => 800000,
            'amount_paid' => 100000,
            'total_installments' => 12,
        ], $overrides));
    }

    /**
     * A loan disbursed exactly inside the given complete-month offset, so the
     * wore-whose disbursement lands in that bucket.
     */
    private function loanDisbursedInMonth(Member $member, int $offsetMonthsBack): Loan
    {
        return $this->activeLoan($member, [
            'principal_amount' => 100000,
            'disbursed_amount' => 100000,
            'outstanding_balance' => 100000,
            'amount_paid' => 0,
            'disbursement_date' => CarbonImmutable::today()->startOfMonth()->subMonths($offsetMonthsBack)->addDays(3),
            'maturity_date' => CarbonImmutable::today()->addMonths(11),
        ]);
    }

    /**
     * A posted loan repayment dated inside the given complete-month offset.
     */
    private function repaymentInMonth(Member $member, Loan $loan, int $offsetMonthsBack, float $amount, float $principal): LoanRepayment
    {
        return LoanRepayment::create([
            'loan_id' => $loan->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'repayment_number' => 'RPT-API-'.fake()->unique()->numberBetween(1, 99999999),
            'amount' => $amount,
            'principal_portion' => $principal,
            'interest_portion' => 0,
            'fee_portion' => 0,
            'payment_date' => CarbonImmutable::today()->startOfMonth()->subMonths($offsetMonthsBack)->addDays(5),
            'payment_method' => 'cash',
            'status' => 'posted',
        ]);
    }

    private function scheduleFor(Loan $loan, array $overrides = []): LoanRepaymentSchedule
    {
        return LoanRepaymentSchedule::create(array_merge([
            'loan_id' => $loan->id,
            'organization_id' => $loan->organization_id,
            'installment_number' => 1,
            'due_date' => CarbonImmutable::today()->subDays(30),
            'principal_amount' => 90000,
            'interest_amount' => 10000,
            'total_amount' => 100000,
            'amount_paid' => 0,
            'outstanding_amount' => 100000,
            'running_balance' => 100000,
            'status' => LoanScheduleInstallmentStatus::Pending->value,
            'days_overdue' => 0,
            'late_fee' => 0,
        ], $overrides));
    }

    private function toolPayload(string $capability, array $arguments = [], string $question = 'Please report.'): array
    {
        return [
            'capability' => $capability,
            'arguments' => $arguments,
            'question' => $question,
        ];
    }

    // ------------------------------------------------------------------
    // Registry, permissions and the public exclusion
    // ------------------------------------------------------------------

    public function test_predictive_capability_is_registered_read_only_and_never_public(): void
    {
        $registry = app(AiToolRegistry::class);

        $this->assertTrue($registry->has('ai.predictive.view'));

        $definition = $registry->definition('ai.predictive.view');

        $this->assertSame(['ai.predictive.view'], $definition['permissions']);
        $this->assertSame([], $definition['arguments'], 'ai.predictive.view must accept no arguments.');
        $this->assertSame(AiToolRegistry::SCOPE_USER_ORG, $registry->scope('ai.predictive.view'));

        $businessCapabilities = array_column($registry->businessCapabilities(), 'handler');
        $this->assertContains(PredictiveInsightTool::class, $businessCapabilities);

        foreach ($registry->publicCapabilities() as $public) {
            $this->assertNotSame('ai.predictive.view', $public['permissions'][0] ?? '');
        }

        $this->assertSame(20, count($registry->businessCapabilities()), 'Adding ai.predictive.view makes 20 business capabilities.');
    }

    public function test_predictive_role_grant_matrix_is_applied_by_the_seeder(): void
    {
        foreach ($this->presetRoles() as $role => $granted) {
            $user = $this->user($role);
            $this->assertSame($granted, $user->can('ai.predictive.view'), "Role {$role} has mismatched ai.predictive.view grant.");
        }
    }

    /**
     * @return array<string, bool>
     */
    private function presetRoles(): array
    {
        $roles = [];

        foreach (self::PREDICTIVE_ROLES as $role) {
            $roles[$role] = true;
        }

        $roles['Secretary'] = false;
        $roles['VICOBA Member'] = false;

        return $roles;
    }

    public function test_predictive_tool_is_denied_without_capability(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Secretary'));

        $this->postJson('/ai/tool', $this->toolPayload('ai.predictive.view'))
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.authorization.denied']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.requested']);
    }

    public function test_vicoba_member_is_denied_predictive_tool(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        $this->actingAs($this->vicobaUser($org, $member));

        $this->postJson('/ai/tool', $this->toolPayload('ai.predictive.view'))
            ->assertStatus(403);

        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.requested']);
    }

    public function test_org_level_forecast_question_is_orchestrated_to_predictive_tool(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Loan Officer'));

        $this->postJson('/ai/chat', ['message' => 'What does the data say about next quarter\'s outlook?'])
            ->assertOk();

        $completed = AuditLog::where('event', 'ai.tool.completed')->first();
        $this->assertNotNull($completed);
        $this->assertSame('ai.predictive.view', $completed->new_values['capability'] ?? null);

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.predictive.generated']);
    }

    // ------------------------------------------------------------------
    // Portfolio forecast
    // ------------------------------------------------------------------

    public function test_portfolio_forecast_is_deterministic_over_complete_history(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        // One 100k disbursement in each of the last 12 complete months, no
        // repayments: outstanding climbs by exactly 100k/month, so the fitted
        // trend is perfectly regular and the forecast extends it exactly.
        for ($offset = 12; $offset >= 1; $offset--) {
            $this->loanDisbursedInMonth($member, $offset);
        }

        $service = app(PredictiveIntelligenceService::class);
        $prediction = $service->refresh(PredictiveInsightType::PortfolioForecast, [$org->id])->first();

        $this->assertNotNull($prediction);
        $this->assertSame('generated', $prediction->status->value);
        $this->assertSame('linear_trend', $prediction->method);
        $this->assertSame('high', $prediction->confidence->value);
        $this->assertSame('good', $prediction->data_quality->value);

        $series = $prediction->series;

        $this->assertCount(12 + 3, $series);

        $historical = array_filter($series, fn (array $row) => ! $row['is_forecast']);
        $forecast = array_values(array_filter($series, fn (array $row) => $row['is_forecast']));

        $this->assertCount(12, $historical);
        $this->assertCount(3, $forecast);

        $lastHistorical = end($historical);
        $this->assertEqualsWithDelta(1200000.0, $lastHistorical['outstanding_end'], 0.01);

        $this->assertSame(
            CarbonImmutable::today()->startOfMonth()->addMonth()->format('Y-m'),
            $forecast[0]['period'],
            'Forecast must begin at the first full month after the data snapshot.',
        );

        $this->assertEqualsWithDelta(1500000.0, $prediction->value_total, 0.01, 'A perfectly regular +100k line must forecast +100k per month.');
        $this->assertEqualsWithDelta(1500000.0, $forecast[2]['outstanding_end'], 0.01);
    }

    public function test_forecast_reports_insufficient_data_without_activity(): void
    {
        $org = $this->makeOrganization();

        $service = app(PredictiveIntelligenceService::class);
        $prediction = $service->refresh(PredictiveInsightType::PortfolioForecast, [$org->id])->first();

        $this->assertNotNull($prediction);
        $this->assertSame('insufficient_data', $prediction->status->value);
        $this->assertSame('insufficient', $prediction->data_quality->value);
        $this->assertNull($prediction->value_total);
        $this->assertSame([], $prediction->series);

        $this->assertStringContainsString('No forecast possible', $prediction->factors[0]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.predictive.generated',
        ]);
    }

    public function test_generation_is_idempotent_per_snapshot(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        for ($offset = 6; $offset >= 1; $offset--) {
            $this->loanDisbursedInMonth($member, $offset);
        }

        $service = app(PredictiveIntelligenceService::class);
        $service->refresh(PredictiveInsightType::PortfolioForecast, [$org->id]);
        $service->refresh(PredictiveInsightType::PortfolioForecast, [$org->id]);

        $this->assertSame(
            1,
            AiPrediction::where('organization_id', $org->id)
                ->where('type', PredictiveInsightType::PortfolioForecast->value)
                ->count(),
            'Re-running the same data snapshot must update the same row, never duplicate it.',
        );
    }

    public function test_forecast_excludes_the_incomplete_current_period(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        for ($offset = 3; $offset >= 1; $offset--) {
            $this->loanDisbursedInMonth($member, $offset);
        }

        $service = app(PredictiveIntelligenceService::class);
        $prediction = $service->refresh(PredictiveInsightType::PortfolioForecast, [$org->id])->first();

        $periods = array_column($prediction->series, 'period');

        $historialRows = array_values(array_filter($prediction->series, fn (array $row) => ! $row['is_forecast']));
        $newestHistorical = end($historialRows);

        $this->assertNotContains(CarbonImmutable::today()->format('Y-m'), $periods, 'The in-progress month must never be part of a series.');
        $this->assertSame(
            CarbonImmutable::today()->startOfMonth()->subMonth()->format('Y-m'),
            $newestHistorical['period'],
            'The newest historical point must be the last COMPLETE month.',
        );

        $this->assertSame(
            CarbonImmutable::today()->startOfMonth()->addMonth()->format('Y-m'),
            $prediction->series[count($prediction->series) - 3]['period'],
        );
    }

    public function test_future_dated_activity_never_leaks_into_forecast_inputs(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        for ($offset = 12; $offset >= 1; $offset--) {
            $loan = $this->loanDisbursedInMonth($member, $offset);
        }

        // A repayment dated in the FUTURE (next month) is not real observability.
        $future = CarbonImmutable::today()->startOfMonth()->addMonths(2)->addDays(5);
        LoanRepayment::create([
            'loan_id' => Loan::where('organization_id', $org->id)->first()->id,
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'repayment_number' => 'RPT-FUT-'.fake()->unique()->numberBetween(1, 99999999),
            'amount' => 9000000,
            'principal_portion' => 9000000,
            'payment_date' => $future,
            'payment_method' => 'cash',
            'status' => 'posted',
        ]);

        $service = app(PredictiveIntelligenceService::class);

        $this->assertLessThanOrEqual(
            CarbonImmutable::today()->toDateString(),
            $service->newestSourceDate($org->id),
            'Future-dated activity must not count as observable data.',
        );

        $prediction = $service->refresh(PredictiveInsightType::PortfolioForecast, [$org->id])->first();

        $this->assertEqualsWithDelta(1500000.0, $prediction->value_total, 0.01, 'Future-dated repayment must not change the forecast.');
    }

    // ------------------------------------------------------------------
    // Delinquency risk
    // ------------------------------------------------------------------

    public function test_delinquency_risk_indicator_is_weighted_and_deterministic(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        // Two loans fully serviced on time (paid exactly on their due date).
        $loanA = $this->activeLoan($member);
        $this->scheduleFor($loanA, [
            'due_date' => CarbonImmutable::today()->subDays(40),
            'status' => LoanScheduleInstallmentStatus::Paid->value,
            'amount_paid' => 100000,
            'outstanding_amount' => 0,
            'paid_date' => CarbonImmutable::today()->subDays(40),
        ]);

        $loanB = $this->activeLoan($member, ['loan_number' => 'LN-DLY-'.$member->id.'-1']);
        $this->scheduleFor($loanB, [
            'due_date' => CarbonImmutable::today()->subDays(30),
            'status' => LoanScheduleInstallmentStatus::Paid->value,
            'amount_paid' => 100000,
            'outstanding_amount' => 0,
            'paid_date' => CarbonImmutable::today()->subDays(30),
        ]);

        // One loan 30 days past due with outstanding principal.
        $loanC = $this->activeLoan($member, ['loan_number' => 'LN-DLY-'.$member->id.'-2']);
        $this->scheduleFor($loanC, [
            'due_date' => CarbonImmutable::today()->subDays(30),
            'status' => LoanScheduleInstallmentStatus::Overdue->value,
            'days_overdue' => 30,
        ]);

        $service = app(PredictiveIntelligenceService::class);
        $prediction = $service->refresh(PredictiveInsightType::DelinquencyRisk, [$org->id])->first();

        $this->assertNotNull($prediction);
        $this->assertSame('generated', $prediction->status->value);
        $this->assertSame('composite', $prediction->method);

        // overdue share 1/3 -> +13.33; severity 30/90 -> +6.67; recurrence 1/3 -> +5; total 25.00.
        $this->assertEqualsWithDelta(25.0, $prediction->value_total, 0.01);
        $this->assertSame([], $prediction->series, 'Risk indicator is an aggregate level, never a member series.');

        $this->assertStringContainsString('level low', $prediction->factors[0]);

        $memberFields = json_encode($prediction->factors);
        $this->assertStringNotContainsString('member', strtolower($memberFields), 'No member-level detail may be derived.');
    }

    public function test_delinquency_risk_is_insufficient_without_active_loans(): void
    {
        $org = $this->makeOrganization();

        $service = app(PredictiveIntelligenceService::class);
        $prediction = $service->refresh(PredictiveInsightType::DelinquencyRisk, [$org->id])->first();

        $this->assertNotNull($prediction);
        $this->assertSame('insufficient_data', $prediction->status->value);
        $this->assertNull($prediction->value_total);
        $this->assertStringContainsString('active loan', $prediction->factors[0]);
    }

    // ------------------------------------------------------------------
    // Cash flow & collections
    // ------------------------------------------------------------------

    public function test_cashflow_forecast_is_deterministic_for_a_steady_net(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        // 100k out each month, 60k in each month over 12 complete months:
        // net is a perfect -40k line, so the forecast is exactly -40k/mo.
        for ($offset = 12; $offset >= 1; $offset--) {
            $loan = $this->loanDisbursedInMonth($member, $offset);
            $this->repaymentInMonth($member, $loan, $offset, 60000, 60000);
        }

        $service = app(PredictiveIntelligenceService::class);
        $prediction = $service->refresh(PredictiveInsightType::CashflowForecast, [$org->id])->first();

        $this->assertNotNull($prediction);
        $this->assertSame('generated', $prediction->status->value);
        $this->assertEqualsWithDelta(-120000.0, $prediction->value_total, 0.01);

        $forecast = array_values(array_filter($prediction->series, fn (array $row) => $row['is_forecast']));
        $this->assertSame(3, count($forecast));
        $this->assertEqualsWithDelta(-40000.0, $forecast[0]['net'], 0.01);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.predictive.generated',
        ]);
    }

    public function test_collection_forecast_includes_due_rates_and_overdue_backlog(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        // One owed schedule due and one posted collection in each complete month,
        // each on its own loan (the schedule unique key is loan + installment).
        for ($offset = 12; $offset >= 1; $offset--) {
            $loan = $this->activeLoan($member);
            $this->scheduleFor($loan, [
                'due_date' => CarbonImmutable::today()->startOfMonth()->subMonths($offset)->addDays(2),
            ]);
            $this->repaymentInMonth($member, $loan, $offset, 60000, 60000);
        }

        // A trail of overdue work: one more loan with a single schedule still
        // outstanding, due 3 months ago.
        $overdueLoan = $this->activeLoan($member);
        $this->scheduleFor($overdueLoan, [
            'due_date' => CarbonImmutable::today()->startOfMonth()->subMonths(3)->addDays(1),
            'status' => LoanScheduleInstallmentStatus::Overdue->value,
            'days_overdue' => 90,
        ]);

        $service = app(PredictiveIntelligenceService::class);
        $prediction = $service->refresh(PredictiveInsightType::CollectionForecast, [$org->id])->first();

        $this->assertNotNull($prediction);
        $this->assertSame('generated', $prediction->status->value);
        $this->assertEqualsWithDelta(60000.0, $prediction->value_total, 0.01);

        $historical = array_values(array_filter($prediction->series, fn (array $row) => ! $row['is_forecast']));
        $last = end($historical);

        $this->assertEqualsWithDelta(100000.0, $last['due'], 0.01);
        $this->assertEqualsWithDelta(60000.0, $last['collected'], 0.01);
        $this->assertEqualsWithDelta(60.0, $last['collection_rate'], 0.01);

        // 12 pending schedules (100k each) + 1 overdue trail (100k) → 1.3M outstanding.
        $this->assertEqualsWithDelta(1300000.0, $last['overdue_amount'], 0.01);
        $this->assertSame(13, $last['overdue_loans']);
    }

    // ------------------------------------------------------------------
    // Isolation, staleness and the dashboard
    // ------------------------------------------------------------------

    public function test_predictions_are_isolated_between_organizations(): void
    {
        $orgA = $this->makeOrganization();
        $orgB = $this->makeOrganization();

        for ($offset = 6; $offset >= 1; $offset--) {
            $this->loanDisbursedInMonth($this->member($orgA), $offset);
        }

        $service = app(PredictiveIntelligenceService::class);
        $service->refresh(PredictiveInsightType::PortfolioForecast, [$orgA->id]);

        // A user scoped to org B can only ever see/generate org B rows.
        $userB = $this->staff($orgB, 'Auditor');
        $context = app(AiContextBuilderService::class)->build($userB);
        $payload = $service->forDashboard($context);

        $this->assertGreaterThanOrEqual(1, count($payload[PredictiveInsightType::PortfolioForecast->value]['rows']));

        foreach ($payload[PredictiveInsightType::PortfolioForecast->value]['rows'] as $row) {
            $this->assertSame($orgB->id, $row['organization_id']);
        }

        $this->assertDatabaseMissing('ai_predictions', ['organization_id' => $orgA->id, 'type' => PredictiveInsightType::CashflowForecast->value]);
    }

    public function test_aged_prediction_is_marked_stale_and_superseded_on_next_snapshot(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        for ($offset = 6; $offset >= 1; $offset--) {
            $this->loanDisbursedInMonth($member, $offset);
        }

        $now = CarbonImmutable::today();
        CarbonImmutable::setTestNow($now);

        try {
            $service = app(PredictiveIntelligenceService::class);
            $first = $service->refresh(PredictiveInsightType::PortfolioForecast, [$org->id])->first();
            $this->assertSame(PredictiveInsightStatus::Generated->value, $first->status->value);

            // Advance past the stale window; the Cached snapshot must regenerate.
            CarbonImmutable::setTestNow($now->addDays(8));

            $second = $service->refresh(PredictiveInsightType::PortfolioForecast, [$org->id])
                ->first(fn (AiPrediction $prediction) => $prediction->data_through->toDateString() === $now->addDays(8)->toDateString());

            $this->assertNotNull($second, 'Advancing past the stale window must regenerate the snapshot.');
            $this->assertSame(PredictiveInsightStatus::Generated->value, $second->status->value);

            $old = $first->fresh();
            $this->assertSame(PredictiveInsightStatus::Superseded->value, $old->status->value, 'The older snapshot must be superseded.');

            $this->assertSame(
                2,
                AiPrediction::where('organization_id', $org->id)
                    ->where('type', PredictiveInsightType::PortfolioForecast->value)
                    ->count(),
            );

            // No new activity, nothing aged → the current snapshot is reused.
            CarbonImmutable::setTestNow($now->addDays(9));
            $reused = $service->refresh(PredictiveInsightType::PortfolioForecast, [$org->id])->first();

            $this->assertSame($second->id, $reused->id, 'A fresh, un-aged snapshot must be reused, not regenerated.');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_dashboard_shows_predictive_outlook_for_holder_and_denies_others(): void
    {
        $org = $this->makeOrganization();

        $holding = $this->staff($org, 'Auditor');
        $this->actingAs($holding)
            ->get(route('ai.intelligence.index'))
            ->assertOk()
            ->assertSee('Predictive outlook');

        $forbidden = $this->staff($org, 'Secretary');
        $this->actingAs($forbidden)
            ->get(route('ai.intelligence.index'))
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.financial_intelligence.viewed']);
    }

    public function test_public_landing_surface_can_never_reach_predictive_capability(): void
    {
        $registry = app(AiToolRegistry::class);

        $publicPermissions = collect($registry->publicCapabilities())
            ->pluck('permissions')
            ->flatten()
            ->all();

        foreach ($publicPermissions as $permission) {
            $this->assertNotSame('ai.predictive.view', $permission);
        }

        $publicHandlers = array_column($registry->publicCapabilities(), 'handler');
        $this->assertNotContains(PredictiveInsightTool::class, $publicHandlers);

        $this->assertTrue($registry->has('ai.predictive.view'));
    }
}
