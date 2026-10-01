<?php

namespace Tests\Feature\AI;

use App\AI\DTOs\AiContextData;
use App\AI\ProactiveIntelligence\Services\ProactiveInsightDetectionService;
use App\AI\ProactiveIntelligence\Services\ProactiveInsightService;
use App\AI\Services\AiInsightResultFormatter;
use App\AI\Services\AiToolRegistry;
use App\AI\Tools\ProactiveInsightsTool;
use App\Enums\JournalEntryStatus;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\PredictionConfidence;
use App\Enums\PredictiveDataQuality;
use App\Enums\PredictiveInsightType;
use App\Enums\ProactiveInsightSeverity;
use App\Enums\ProactiveInsightStatus;
use App\Models\AccountingPeriod;
use App\Models\AiInsight;
use App\Models\AiPrediction;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Phase 11.9 Proactive Intelligence: the deterministic rules, the idempotent
 * and deduplicated persistence, human acknowledge/resolve/dismiss transitions,
 * domain-driven expiration, tenant/branch isolation, the permission surface, the
 * predictive-outlook integration, the AI tool + formatter, and the in-app
 * notification inbox.
 *
 * Every assertion is about advisory, read-only behavior: an insight never
 * changes a financial record, is never created/dismissed by the AI, and is
 * only retired when its domain condition stops holding or a human decides so.
 */
class AiProactiveIntelligenceTest extends AiTestCase
{
    /**
     * Staff roles granted ai.insights.view by the seeder; Secretary and
     * VICOBA Member hold none (the alert surface is staff-facing advisory
     * signal only).
     */
    private const INSIGHTS_ROLES = [
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
            'principal_amount' => 100000,
            'disbursed_amount' => 100000,
            'total_amount' => 120000,
            'outstanding_balance' => 100000,
            'amount_paid' => 0,
            'total_installments' => 12,
        ], $overrides));
    }

    private function overdueInstallment(Loan $loan, int $daysOverdue, float $total = 100000.0): LoanRepaymentSchedule
    {
        return LoanRepaymentSchedule::create([
            'loan_id' => $loan->id,
            'organization_id' => $loan->organization_id,
            'installment_number' => 1,
            'due_date' => Carbon::today()->subDays($daysOverdue),
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

    private function scheduleInMonth(Loan $loan, Carbon $month, float $total, int $installment = 1): LoanRepaymentSchedule
    {
        return LoanRepaymentSchedule::create([
            'loan_id' => $loan->id,
            'organization_id' => $loan->organization_id,
            'installment_number' => $installment,
            'due_date' => $month->copy()->addDays(10)->toDateString(),
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

    private function postedRepayment(Loan $loan, Member $member, Carbon $when, float $amount): LoanRepayment
    {
        return LoanRepayment::create([
            'loan_id' => $loan->id,
            'organization_id' => $loan->organization_id,
            'branch_id' => $loan->branch_id,
            'member_id' => $member->id,
            'repayment_number' => 'RPT-PI-'.fake()->unique()->numberBetween(1, 99999999),
            'amount' => $amount,
            'principal_portion' => $amount,
            'interest_portion' => 0,
            'fee_portion' => 0,
            'payment_date' => $when->toDateString(),
            'payment_method' => 'cash',
            'status' => 'posted',
        ]);
    }

    private function draftJournalEntry(Organization $org, int $daysAgo): JournalEntry
    {
        $period = AccountingPeriod::create([
            'organization_id' => $org->id,
            'name' => 'Period '.Carbon::today()->format('Y-m'),
            'start_date' => Carbon::today()->startOfMonth()->toDateString(),
            'end_date' => Carbon::today()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        $journal = JournalEntry::create([
            'organization_id' => $org->id,
            'journal_number' => 'JE-PI-'.fake()->unique()->numberBetween(1, 99999999),
            'accounting_period_id' => $period->id,
            'entry_date' => Carbon::today()->subDays($daysAgo)->toDateString(),
            'description' => 'Stale draft for proactive test.',
            'status' => JournalEntryStatus::Draft->value,
        ]);

        // created_at is not fillable on JournalEntry; the draft-gap rule ages on
        // that column, so persist the aged timestamp after creation.
        $journal->created_at = Carbon::today()->subDays($daysAgo);
        $journal->save();

        return $journal->refresh();
    }

    private function delinquencyPrediction(Organization $org, float $score, string $status = 'generated', string $dataThrough = 'today'): AiPrediction
    {
        $today = Carbon::today()->toDateString();

        return AiPrediction::create([
            'organization_id' => $org->id,
            'type' => PredictiveInsightType::DelinquencyRisk->value,
            'status' => $status,
            'scope' => 'organization',
            'method' => 'statistical_baseline',
            'model_version' => 'statistical-baseline-v1',
            'target_period' => Carbon::today()->startOfMonth()->addMonth()->format('Y-m'),
            'data_through' => $dataThrough === 'today'
                ? $today
                : Carbon::today()->addDays((int) $dataThrough)->toDateString(),
            'data_from' => Carbon::today()->startOfMonth()->subMonths(5)->toDateString(),
            'horizon' => 3,
            'confidence' => PredictionConfidence::Medium->value,
            'data_quality' => $status === 'poor_quality_data'
                ? PredictiveDataQuality::Limited->value
                : PredictiveDataQuality::Good->value,
            'value_total' => $score,
            'currency' => 'TZS',
            'series' => [],
            'factors' => [],
            'assumptions' => ['method' => 'statistical_baseline'],
            'explanation' => 'Proactive intelligence test prediction.',
            'generated_at' => new \DateTime,
        ]);
    }

    private function context(User $user, array $organizationIds, array $branchIds = []): AiContextData
    {
        return new AiContextData(
            userId: (int) $user->id,
            isSuperAdmin: $user->hasRole('Super Administrator'),
            roles: $user->getRoleNames()->all(),
            permissions: $user->getAllPermissions()->pluck('name')->all(),
            organizationIds: $organizationIds,
            branchIds: $branchIds,
            vicobaGroupIds: [],
            memberId: null,
        );
    }

    private function toolPayload(string $capability, array $arguments = [], string $question = 'Please report.'): array
    {
        return [
            'capability' => $capability,
            'arguments' => $arguments,
            'question' => $question,
        ];
    }

    private function overdueSetup(Organization $org, int $dpd = 40): Loan
    {
        $member = $this->member($org);
        $loan = $this->activeLoan($member, ['outstanding_balance' => 100000]);
        $this->overdueInstallment($loan, $dpd);

        return $loan;
    }

    /**
     * A loan book of $count equal active loans, the first of which is overdue.
     * Because every loan holds an equal share (1/count ≤ 25%), no single-loan
     * concentration insight fires and the book raises exactly the overdue rule
     * — which keeps count/empty assertions deterministic.
     */
    private function balancedBook(Organization $org, int $count = 4, float $amount = 100000.0, int $dpd = 10): Loan
    {
        $loans = [];

        for ($i = 0; $i < $count; $i++) {
            $loans[] = $this->activeLoan($this->member($org), ['outstanding_balance' => $amount]);
        }

        $this->overdueInstallment($loans[0], $dpd);

        return $loans[0];
    }

    // ------------------------------------------------------------------
    // Service generation, idempotency and auditing
    // ------------------------------------------------------------------

    public function test_generation_persists_insights_idempotently_and_audits(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        $this->overdueSetup($org, 10);
        $this->activeLoan($member, ['maturity_date' => Carbon::today()->addDays(3)->toDateString()]);
        $this->activeLoan($member, ['maturity_date' => Carbon::today()->addDays(5)->toDateString()]);
        $this->activeLoan($member, ['maturity_date' => Carbon::today()->addDays(7)->toDateString()]);

        $service = app(ProactiveInsightService::class);
        $first = $service->generate($org->id);

        $this->assertGreaterThanOrEqual(2, $first['created']);
        $this->assertSame(0, $first['expired']);

        $rows = AiInsight::forOrganization($org->id)->get();

        $this->assertGreaterThanOrEqual(2, $rows->count());
        $this->assertSame($rows->count(), $rows->pluck('dedup_key')->unique()->count(), 'Every insight must carry a unique dedup key.');
        foreach ($rows as $row) {
            $this->assertTrue($row->status->isOpen());
            $this->assertNotNull($row->generated_at);
        }

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.insights.generated']);

        $second = $service->generate($org->id);

        $this->assertSame(0, $second['created'], 'Re-running the same pass must not create new rows.');
        $this->assertGreaterThanOrEqual($rows->count(), $second['updated']);
        $this->assertSame($rows->count(), AiInsight::forOrganization($org->id)->count(), 'No dedup duplication after a second pass.');
    }

    // ------------------------------------------------------------------
    // Detection rules
    // ------------------------------------------------------------------

    public function test_detection_raises_rule_specific_insights(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        // Overdue + concentration (one loan dominates the book).
        $big = $this->activeLoan($member, ['outstanding_balance' => 950000]);
        $this->overdueInstallment($big, 40);
        $this->activeLoan($member, ['outstanding_balance' => 50000]);

        // Collection decline: 100% last month, 0% this month.
        $collectionLoan = $this->activeLoan($member, ['outstanding_balance' => 100000]);
        $currentMonth = Carbon::today()->startOfMonth()->subMonth();
        $previousMonth = Carbon::today()->startOfMonth()->subMonths(2);
        $this->scheduleInMonth($collectionLoan, $previousMonth, 100000, 1);
        $this->postedRepayment($collectionLoan, $member, $previousMonth->copy()->addDays(15), 100000);
        $this->scheduleInMonth($collectionLoan, $currentMonth, 100000, 2);

        // Operational gap: stale pending application + stale journal draft.
        LoanApplication::factory()->create([
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'member_id' => $member->id,
            'loan_plan_id' => LoanPlan::factory()->create(['organization_id' => $org->id])->id,
            'application_date' => Carbon::today()->subDays(10)->toDateString(),
            'status' => LoanApplicationStatus::Submitted->value,
        ]);
        $this->draftJournalEntry($org, 10);

        $detected = app(ProactiveInsightDetectionService::class)->detect($org->id);
        $rules = array_column($detected, 'rule');

        $this->assertContains('overdue_loan', $rules);
        $this->assertContains('portfolio_concentration', $rules);
        $this->assertContains('collection_decline', $rules);
        $this->assertContains('operational_pending_applications', $rules);
        $this->assertContains('accounting_draft_entries', $rules);

        $overdue = collect($detected)->firstWhere('rule', 'overdue_loan');
        $this->assertSame('overdue_loan', $overdue['type']);
        $this->assertSame(ProactiveInsightSeverity::Warning->value, $overdue['severity']);
    }

    public function test_detection_respects_branch_scope(): void
    {
        $org = $this->makeOrganization();
        $branchA = $this->branch($org);
        $branchB = $this->branch($org);

        $memberA = $this->member($org);
        $memberA->update(['branch_id' => $branchA->id]);

        $loanA = $this->activeLoan($memberA, ['branch_id' => $branchA->id]);
        $this->overdueInstallment($loanA, 15);

        $memberB = $this->member($org);
        $memberB->update(['branch_id' => $branchB->id]);
        $loanB = $this->activeLoan($memberB, ['branch_id' => $branchB->id]);
        $this->overdueInstallment($loanB, 15);

        $detector = app(ProactiveInsightDetectionService::class);

        $this->assertNotEmpty($detector->detect($org->id));

        $branchBRules = array_column($detector->detect($org->id, [(int) $branchB->id]), 'source_id');
        $this->assertContains((int) $loanB->id, $branchBRules);
        $this->assertNotContains((int) $loanA->id, $branchBRules);

        $branchARules = array_column($detector->detect($org->id, [(int) $branchA->id]), 'source_id');
        $this->assertContains((int) $loanA->id, $branchARules);
        $this->assertNotContains((int) $loanB->id, $branchARules);

        $emptyBranch = $this->branch($org);
        $this->assertEmpty(
            $detector->detect($org->id, [(int) $emptyBranch->id]),
            'A branch with no financial records raises no insights.',
        );
    }

    // ------------------------------------------------------------------
    // Deduplication, dismissed override and reopen semantics
    // ------------------------------------------------------------------

    public function test_dismissed_insight_is_never_overwritten(): void
    {
        $org = $this->makeOrganization();
        $loan = $this->balancedBook($org);

        $service = app(ProactiveInsightService::class);
        $service->generate($org->id);

        $insight = AiInsight::forOrganization($org->id)->firstOrFail();
        $service->dismiss($insight, $this->staff($org, 'Loan Officer'));

        $this->assertSame(ProactiveInsightStatus::Dismissed, $insight->fresh()->status);

        $service->generate($org->id);

        $this->assertSame(ProactiveInsightStatus::Dismissed, $insight->fresh()->status, 'Dismissed rows must keep their human override across passes.');
        $this->assertSame(1, AiInsight::forOrganization($org->id)->count());

        // The condition is still present, so a fresh overlay never appears: the
        // dismissed row still occupies the dedup key.
        $this->assertDatabaseMissing('ai_insights', [
            'organization_id' => $org->id,
            'status' => ProactiveInsightStatus::New->value,
        ]);
    }

    public function test_resolved_insight_reopens_when_condition_reoccurs(): void
    {
        $org = $this->makeOrganization();
        $loan = $this->overdueSetup($org, 10);

        $service = app(ProactiveInsightService::class);
        $service->generate($org->id);

        $insight = AiInsight::forOrganization($org->id)->firstOrFail();
        $this->assertNotNull($loan);
        $service->resolve($insight, $this->staff($org, 'Loan Officer'));

        $this->assertSame(ProactiveInsightStatus::Resolved, $insight->fresh()->status);

        $second = $service->generate($org->id);
        $this->assertSame(0, $second['created']);

        $fresh = $insight->fresh();
        $this->assertSame(ProactiveInsightStatus::New, $fresh->status, 'A resolved insight whose condition re-occurs reopens as new.');
        $this->assertNull($fresh->resolved_by);
        $this->assertNull($fresh->resolved_at);
    }

    // ------------------------------------------------------------------
    // Human acknowledge / resolve / dismiss lifecycle
    // ------------------------------------------------------------------

    public function test_acknowledge_resolve_dismiss_transitions_are_audited(): void
    {
        $org = $this->makeOrganization();
        $this->overdueSetup($org, 10);
        $this->activeLoan($this->member($org), ['maturity_date' => Carbon::today()->addDays(3)->toDateString()]);
        $this->activeLoan($this->member($org), ['maturity_date' => Carbon::today()->addDays(4)->toDateString()]);
        $this->activeLoan($this->member($org), ['maturity_date' => Carbon::today()->addDays(5)->toDateString()]);

        app(ProactiveInsightService::class)->generate($org->id);

        $user = $this->staff($org, 'Loan Officer');
        $service = app(ProactiveInsightService::class);

        $toAcknowledge = AiInsight::forOrganization($org->id)
            ->where('severity', 'warning')->first() ?? AiInsight::forOrganization($org->id)->first();

        $ack = $service->acknowledge($toAcknowledge, $user);
        $this->assertSame(ProactiveInsightStatus::Acknowledged, $ack->status);
        $this->assertSame($user->id, $ack->acknowledged_by);
        $this->assertNotNull($ack->acknowledged_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.insight.acknowledged']);

        // Acknowledge from acknowledged state is a no-op.
        $this->assertSame(ProactiveInsightStatus::Acknowledged, $service->acknowledge($ack, $user)->status);

        $resolved = $service->resolve($ack, $user);
        $this->assertSame(ProactiveInsightStatus::Resolved, $resolved->status);
        $this->assertSame($user->id, $resolved->resolved_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.insight.resolved']);

        // Resolve from resolved is a no-op.
        $this->assertSame(ProactiveInsightStatus::Resolved, $service->resolve($resolved, $user)->status);

        $toDismiss = AiInsight::forOrganization($org->id)->where('status', ProactiveInsightStatus::New->value)->first();
        $dismissed = $service->dismiss($toDismiss, $user);
        $this->assertSame(ProactiveInsightStatus::Dismissed, $dismissed->status);
        $this->assertSame($user->id, $dismissed->dismissed_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.insight.dismissed']);

        // Dismiss from dismissed is a no-op.
        $this->assertSame(ProactiveInsightStatus::Dismissed, $service->dismiss($dismissed, $user)->status);
    }

    // ------------------------------------------------------------------
    // Expiration (domain-driven retirement)
    // ------------------------------------------------------------------

    public function test_stale_open_insight_expires_when_condition_is_gone(): void
    {
        $org = $this->makeOrganization();
        $loan = $this->balancedBook($org);

        app(ProactiveInsightService::class)->generate($org->id);

        $row = AiInsight::forOrganization($org->id)->firstOrFail();
        $row->update(['generated_at' => Carbon::today()->subDays((int) config('proactive-intelligence.stale_after_days', 7) + 1)]);

        // The condition disappears: the overdue installment is fully repaid.
        $loan->repaymentSchedule()->update([
            'outstanding_amount' => 0,
            'status' => LoanScheduleInstallmentStatus::Paid->value,
        ]);

        $this->assertEmpty(app(ProactiveInsightDetectionService::class)->detect($org->id));

        $expired = app(ProactiveInsightService::class)->expireFor($org->id);

        $this->assertSame(1, $expired);
        $fresh = $row->fresh();
        $this->assertSame(ProactiveInsightStatus::Expired, $fresh->status);
        $this->assertNotNull($fresh->expired_at);
    }

    public function test_fresh_or_still_present_insight_does_not_expire(): void
    {
        $org = $this->makeOrganization();
        $loan = $this->overdueSetup($org, 10);

        $service = app(ProactiveInsightService::class);
        $service->generate($org->id);

        $row = AiInsight::forOrganization($org->id)->firstOrFail();

        // Still present in the current pass: not expired even if aged.
        $row->update(['generated_at' => Carbon::today()->subDays(30)]);
        $this->assertSame(0, $service->expireFor($org->id));
        $this->assertSame(ProactiveInsightStatus::New, $row->fresh()->status);

        // The condition vanishes while the row stays fresh: not expired yet.
        $loan->repaymentSchedule()->update(['outstanding_amount' => 0, 'status' => LoanScheduleInstallmentStatus::Paid->value]);
        $row->update(['generated_at' => Carbon::today()]);
        $this->assertSame(0, $service->expireFor($org->id));
        $this->assertSame(ProactiveInsightStatus::New, $row->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Tenant and branch isolation (dashboard surface)
    // ------------------------------------------------------------------

    public function test_dashboard_scopes_insights_to_context_organizations_and_branches(): void
    {
        $orgA = $this->makeOrganization();
        $orgB = $this->makeOrganization();

        $loanA = $this->overdueSetup($orgA, 10);
        $this->overdueSetup($orgB, 10);

        app(ProactiveInsightService::class)->generate($orgA->id);
        app(ProactiveInsightService::class)->generate($orgB->id);

        $user = $this->staff($orgA, 'Loan Officer');
        $service = app(ProactiveInsightService::class);

        $orgContext = $this->context($user, [$orgA->id]);
        $orgInsights = $service->forDashboard($orgContext);

        $this->assertCount(AiInsight::forOrganization($orgA->id)->count(), $orgInsights);
        $this->assertFalse($orgInsights->contains(fn (AiInsight $i) => $i->organization_id === $orgB->id));

        // Branch scoping: keep only insights for the authorized branch.
        $branch = $loanA->branch;
        $branchContext = $this->context($user, [$orgA->id], [(int) $branch->id]);
        $branchInsights = $service->forDashboard($branchContext);

        $this->assertGreaterThan(0, $branchInsights->count());

        $otherBranch = $this->branch($orgA);
        $emptyContext = $this->context($user, [$orgA->id], [(int) $otherBranch->id]);
        $this->assertCount(0, $service->forDashboard($emptyContext), 'Insights from other branches must be filtered out.');
    }

    // ------------------------------------------------------------------
    // Authorization: role matrix, route and tool gating
    // ------------------------------------------------------------------

    public function test_insights_role_grant_matrix_is_applied_by_the_seeder(): void
    {
        foreach ($this->insightPresetRoles() as $role => $granted) {
            $user = $this->user($role);
            $this->assertSame(
                $granted,
                $user->can('ai.insights.view'),
                "Role {$role} has mismatched ai.insights.view grant.",
            );
        }
    }

    private function insightPresetRoles(): array
    {
        $roles = [];
        foreach (self::INSIGHTS_ROLES as $role) {
            $roles[$role] = true;
        }
        $roles['Secretary'] = false;
        $roles['VICOBA Member'] = false;

        return $roles;
    }

    public function test_intelligence_page_renders_for_insights_holder_and_denies_others(): void
    {
        $org = $this->makeOrganization();
        $this->overdueSetup($org, 10);
        app(ProactiveInsightService::class)->generate($org->id);

        $holder = $this->staff($org, 'Loan Officer');
        $this->actingAs($holder);
        $this->get('/ai/intelligence')->assertOk()->assertSee('Proactive insights');

        $this->actingAs($this->staff($org, 'Secretary'));
        $this->get('/ai/intelligence')->assertForbidden();
    }

    public function test_cross_org_insight_action_is_forbidden(): void
    {
        $orgA = $this->makeOrganization();
        $orgB = $this->makeOrganization();

        $this->overdueSetup($orgB, 10);
        app(ProactiveInsightService::class)->generate($orgB->id);
        $foreign = AiInsight::forOrganization($orgB->id)->firstOrFail();

        $this->actingAs($this->staff($orgA, 'Loan Officer'));

        $this->post(
            route('ai.intelligence.insights.dismiss', $foreign),
            [],
            ['Accept' => 'application/json'],
        )->assertForbidden();

        $this->actingAs($this->staff($orgA, 'Secretary'));
        $this->post(
            route('ai.intelligence.insights.resolve', $foreign),
            [],
            ['Accept' => 'application/json'],
        )->assertForbidden();
    }

    public function test_insights_tool_is_denied_without_capability(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Secretary'));

        $this->postJson('/ai/tool', $this->toolPayload('ai.insights.view'))
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.authorization.denied']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.requested']);
    }

    // ------------------------------------------------------------------
    // Predictive-outlook integration
    // ------------------------------------------------------------------

    public function test_predictive_outlook_insights_follow_prediction_state(): void
    {
        $org = $this->makeOrganization();
        $this->delinquencyPrediction($org, 80);

        $detector = app(ProactiveInsightDetectionService::class);
        $detected = $detector->detect($org->id);

        $risk = collect($detected)->firstWhere('rule', 'predictive_delinquency_risk');
        $this->assertNotNull($risk, 'A high delinquency-risk outlook must surface as a proactive insight.');
        $this->assertSame(ProactiveInsightSeverity::Warning->value, $risk['severity']);
        $this->assertSame('prediction', $risk['source_type']);

        // A poor-quality snapshot triggers the quality-gate notice instead.
        $this->delinquencyPrediction($org, 50, 'poor_quality_data', '1');
        $detectedAgain = $detector->detect($org->id);
        $quality = collect($detectedAgain)->firstWhere('rule', 'predictive_quality_gate');
        $this->assertNotNull($quality, 'A poor-quality predictive snapshot must raise the quality-gate insight.');
        $this->assertSame(ProactiveInsightSeverity::Notice->value, $quality['severity']);
    }

    // ------------------------------------------------------------------
    // AI tool and formatter
    // ------------------------------------------------------------------

    public function test_insights_tool_returns_open_insights_for_holder(): void
    {
        $org = $this->makeOrganization();
        $this->overdueSetup($org, 10);
        app(ProactiveInsightService::class)->generate($org->id);

        $this->actingAs($this->staff($org, 'Loan Officer'));

        $response = $this->postJson('/ai/tool', $this->toolPayload('ai.insights.view'));

        $response->assertOk()
            ->assertJsonPath('data.capability', 'ai.insights.view');

        $result = $response->json('data.result');

        $this->assertGreaterThanOrEqual(1, $result['open_count']);
        $this->assertCount($result['open_count'], $result['insights']);
        $this->assertContains(
            $result['insights'][0]['status'],
            [ProactiveInsightStatus::New->value, ProactiveInsightStatus::Read->value, ProactiveInsightStatus::Acknowledged->value],
        );
        $this->assertTrue($result['insights'][0]['open']);

        $completed = AuditLog::where('event', 'ai.tool.completed')->first();
        $this->assertNotNull($completed);
        $this->assertSame('ai.insights.view', $completed->new_values['capability'] ?? null);
    }

    public function test_insights_capability_is_registered_read_only_never_public_and_formatter_delimited(): void
    {
        $registry = app(AiToolRegistry::class);

        $this->assertTrue($registry->has('ai.insights.view'));
        $definition = $registry->definition('ai.insights.view');
        $this->assertSame(['ai.insights.view'], $definition['permissions']);
        $this->assertSame([], $definition['arguments'], 'ai.insights.view must accept no arguments.');
        $this->assertSame(AiToolRegistry::SCOPE_USER_ORG, $registry->scope('ai.insights.view'));

        $business = array_column($registry->businessCapabilities(), 'handler');
        $this->assertContains(ProactiveInsightsTool::class, $business);

        foreach ($registry->publicCapabilities() as $public) {
            $this->assertNotSame('ai.insights.view', $public['permissions'][0] ?? '');
        }

        $formatted = AiInsightResultFormatter::format([
            'open_count' => 1,
            'insights' => [['title' => 'Loan overdue', 'severity' => 'warning']],
        ]);
        $this->assertStringContainsString(AiInsightResultFormatter::OPEN_BLOCK, $formatted->content);
        $this->assertStringContainsString(AiInsightResultFormatter::CLOSE_BLOCK, $formatted->content);
        $this->assertStringContainsString('advisory alerts', $formatted->content);
        $this->assertStringContainsString('never performed by the AI', $formatted->content);
    }

    public function test_org_level_alert_question_is_orchestrated_to_insights_tool(): void
    {
        $org = $this->makeOrganization();
        $this->overdueSetup($org, 10);
        app(ProactiveInsightService::class)->generate($org->id);

        $this->actingAs($this->staff($org, 'Loan Officer'));

        $this->postJson('/ai/chat', ['message' => 'What proactive insights are open for our organization right now?'])
            ->assertOk();

        $completed = AuditLog::where('event', 'ai.tool.completed')->first();
        $this->assertNotNull($completed);
        $this->assertSame('ai.insights.view', $completed->new_values['capability'] ?? null);
    }

    // ------------------------------------------------------------------
    // In-app notifications and inbox
    // ------------------------------------------------------------------

    public function test_new_insight_notifies_only_permissioned_users_in_org(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org, 'Loan Officer');
        $member = $this->member($org);
        $vicoba = $this->vicobaUser($org, $member);

        $outside = $this->staff($this->makeOrganization(), 'Loan Officer');

        $this->balancedBook($org);
        app(ProactiveInsightService::class)->generate($org->id);

        $this->assertSame(
            1,
            DatabaseNotification::where('notifiable_id', $staff->id)->count(),
            'Loan Officers in the affected organization must be notified.',
        );
        $this->assertSame(
            0,
            DatabaseNotification::where('notifiable_id', $vicoba->id)->count(),
            'VICOBA Members hold no ai.insights.view and must not be notified.',
        );
        $this->assertSame(
            0,
            DatabaseNotification::where('notifiable_id', $outside->id)->count(),
            'Staff in other organizations must not be notified.',
        );

        $notification = DatabaseNotification::where('notifiable_id', $staff->id)->firstOrFail();
        $this->assertSame('App\Notifications\AiInsightNotification', $notification->type);
        $this->assertArrayHasKey('title', $notification->data);
        $this->assertArrayHasKey('url', $notification->data);
    }

    public function test_notification_inbox_lists_and_marks_read_only_own_rows(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org, 'Loan Officer');
        $this->overdueSetup($org, 10);
        app(ProactiveInsightService::class)->generate($org->id);

        $notification = DatabaseNotification::where('notifiable_id', $staff->id)->firstOrFail();

        $this->actingAs($staff);
        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Loan overdue');

        $this->post(route('notifications.read', $notification))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at, 'Reading a notification marks it read for its owner.');

        $this->post(route('notifications.read-all'))->assertRedirect();

        // A different user cannot read another user's notification row.
        $stranger = $this->staff($org, 'Accountant');
        $this->actingAs($stranger);
        $this->post(route('notifications.read', $notification), [], ['Accept' => 'application/json'])
            ->assertStatus(404);
    }
}
