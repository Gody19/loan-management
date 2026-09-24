<?php

namespace Tests\Feature\AI;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use App\Models\LoanPlanCollateralRule;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\ShareAccount;
use App\Models\WelfareAccount;
use App\Services\CollateralRequirementService;
use App\Services\FinancialStatementService;
use App\Services\GuarantorEligibilityService;
use App\Services\LoanEligibilityService;

/**
 * Financial correctness and tenant isolation of the Phase 11.3 business-data
 * tools. Every financial figure that reaches the AI is asserted to be exactly
 * the figure produced by the authoritative FinancePro service.
 */
class AiToolFinancialTest extends AiTestCase
{
    private function memberPayload(string $capability, Member $member, array $extra = []): array
    {
        return [
            'capability' => $capability,
            'arguments' => array_merge(['member_number' => $member->member_number], $extra),
            'question' => 'Please report.',
        ];
    }

    private function accountsForMember(Member $member, array $balances = []): void
    {
        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'current_balance' => $balances['savings'] ?? 500000,
        ]);

        ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'total_shares' => 50,
            'total_value' => 750000,
        ]);

        WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'current_balance' => 120000,
        ]);
    }

    private function loanFor(Member $member, array $overrides = []): Loan
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
            'amount_paid' => 280000,
        ], $overrides));
    }

    public function test_member_tools_scoped_to_acting_users_organization(): void
    {
        $orgA = $this->makeOrganization();
        $orgB = $this->makeOrganization();
        $staffB = $this->staff($orgB);
        $memberA = $this->member($orgA);

        $this->actingAs($staffB);

        $this->postJson('/ai/tool', $this->memberPayload('ai.member.view', $memberA))
            ->assertStatus(403)
            ->assertJsonPath('category', 'not_found');

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.tool.denied']);

        $this->postJson('/ai/tool', $this->memberPayload(
            'ai.member.financial_summary',
            $this->member($orgB),
        ))->assertOk();
    }

    public function test_vicoba_member_is_owner_only_for_member_and_loan_tools(): void
    {
        $org = $this->makeOrganization();
        $member1 = $this->member($org);
        $member2 = $this->member($org);
        $loan1 = $this->loanFor($member1);
        $loan2 = $this->loanFor($member2);

        $user = $this->vicobaUser($org, $member1);

        $this->actingAs($user);

        $this->postJson('/ai/tool', $this->memberPayload('ai.member.view', $member1))
            ->assertOk()
            ->assertJsonPath('data.result.member_number', $member1->member_number);

        $this->postJson('/ai/tool', $this->memberPayload('ai.member.view', $member2))
            ->assertStatus(403)
            ->assertJsonPath('category', 'not_found');

        $this->postJson('/ai/tool', [
            'capability' => 'ai.member.financial_summary',
            'arguments' => ['member_number' => $member2->member_number],
            'question' => 'Report.',
        ])
            ->assertStatus(403)
            ->assertJsonPath('category', 'not_found');

        $this->postJson('/ai/tool', [
            'capability' => 'ai.loan.view',
            'arguments' => ['loan_number' => $loan1->loan_number],
            'question' => 'Report.',
        ])
            ->assertOk()
            ->assertJsonPath('data.result.loan_number', $loan1->loan_number);

        $this->postJson('/ai/tool', [
            'capability' => 'ai.loan.view',
            'arguments' => ['loan_number' => $loan2->loan_number],
            'question' => 'Report.',
        ])
            ->assertStatus(403)
            ->assertJsonPath('category', 'not_found');
    }

    public function test_financial_summary_matches_authoritative_service(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);
        $member = $this->member($org);
        $this->accountsForMember($member);

        $this->actingAs($staff);

        $response = $this->postJson('/ai/tool', $this->memberPayload('ai.member.financial_summary', $member));

        $response->assertOk();
        $result = $response->json('data.result');

        $expected = (new FinancialStatementService)->getMemberFinancialSummary($member);

        $this->assertEquals((float) $expected['total_savings'], $result['total_savings']);
        $this->assertSame((int) $expected['savings_accounts_count'], $result['savings_accounts_count']);
        $this->assertSame((int) $expected['total_shares'], $result['total_shares']);
        $this->assertEquals((float) $expected['total_share_value'], $result['total_share_value']);
        $this->assertEquals((float) $expected['welfare_balance'], $result['welfare_balance']);
    }

    public function test_account_summaries_pass_through_stored_balances_only(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);
        $member = $this->member($org);
        $this->accountsForMember($member, ['savings' => 260000]);

        $this->actingAs($staff);

        $savings = $this->postJson('/ai/tool', $this->memberPayload('ai.member.savings_summary', $member));
        $savings->assertOk()
            ->assertJsonPath('data.result.count', 1);

        $this->assertEquals(260000.0, $savings->json('data.result.total_savings'));
        $this->assertEquals(260000.0, $savings->json('data.result.accounts.0.current_balance'));

        $this->postJson('/ai/tool', $this->memberPayload('ai.member.share_summary', $member))
            ->assertOk();

        $this->postJson('/ai/tool', $this->memberPayload('ai.member.welfare_summary', $member))
            ->assertOk();
    }

    public function test_member_loans_list_is_server_side_bounded(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);
        $member = $this->member($org);

        Loan::factory()->count(30)->create([
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
        ]);

        $this->actingAs($staff);

        $this->postJson('/ai/tool', $this->memberPayload(
            'ai.member.loans',
            $member,
            ['limit' => 100],
        ))
            ->assertOk()
            ->assertJsonPath('data.result.limit', 25)
            ->assertJsonPath('data.result.count', 25);
    }

    public function test_loan_view_uses_stored_balances_and_authoritative_days_past_due(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);
        $member = $this->member($org);
        $loan = $this->loanFor($member, ['outstanding_balance' => 800000, 'amount_paid' => 280000]);

        LoanRepaymentSchedule::factory()->overdue()->create([
            'loan_id' => $loan->id,
            'organization_id' => $org->id,
            'due_date' => now()->subDays(12)->toDateString(),
            'outstanding_amount' => 10000,
        ]);

        $this->actingAs($staff);

        $response = $this->postJson('/ai/tool', [
            'capability' => 'ai.loan.view',
            'arguments' => ['loan_number' => $loan->loan_number],
            'question' => 'Report.',
        ]);

        $response->assertOk();
        $result = $response->json('data.result');

        $this->assertSame($loan->loan_number, $result['loan_number']);
        $this->assertEquals(800000.0, $result['outstanding_balance']);
        $this->assertEquals(280000.0, $result['amount_paid']);
        $this->assertSame($member->member_number, $result['member_number']);

        $this->assertArrayHasKey('days_past_due', $result);
        $this->assertGreaterThan(0, (int) $result['days_past_due']);
    }

    public function test_loan_repayments_distinguish_posted_from_reversed(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);
        $member = $this->member($org);
        $loan = $this->loanFor($member);

        LoanRepayment::factory()->posted()->create([
            'loan_id' => $loan->id,
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'amount' => 200000,
            'payment_date' => now()->subDays(5)->toDateString(),
        ]);

        LoanRepayment::factory()->reversed()->create([
            'loan_id' => $loan->id,
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'amount' => 50000,
            'payment_date' => now()->subDays(3)->toDateString(),
            'reversal_date' => now()->subDay(),
        ]);

        $this->actingAs($staff);

        $response = $this->postJson('/ai/tool', [
            'capability' => 'ai.loan.repayments',
            'arguments' => ['loan_number' => $loan->loan_number],
            'question' => 'Report.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.result.total_count', 2)
            ->assertJsonPath('data.result.posted_count', 1)
            ->assertJsonPath('data.result.reversed_count', 1);

        $rows = collect($response->json('data.result.repayments'));

        $posted = $rows->firstWhere('status', 'posted');
        $reversed = $rows->firstWhere('status', 'reversed');

        $this->assertFalse($posted['is_reversed']);
        $this->assertTrue($reversed['is_reversed']);
        $this->assertNotNull($reversed['reversal_date']);
        $this->assertNull($posted['reversal_reason']);
    }

    public function test_loan_repayments_respect_date_window(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);
        $member = $this->member($org);
        $loan = $this->loanFor($member);

        LoanRepayment::factory()->posted()->create([
            'loan_id' => $loan->id,
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'payment_date' => now()->subDays(10)->toDateString(),
        ]);

        LoanRepayment::factory()->posted()->create([
            'loan_id' => $loan->id,
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'payment_date' => now()->subDays(2)->toDateString(),
        ]);

        $this->actingAs($staff);

        $this->postJson('/ai/tool', [
            'capability' => 'ai.loan.repayments',
            'arguments' => [
                'loan_number' => $loan->loan_number,
                'from' => now()->subDays(4)->toDateString(),
                'to' => now()->toDateString(),
            ],
            'question' => 'Report.',
        ])
            ->assertOk()
            ->assertJsonPath('data.result.total_count', 1)
            ->assertJsonPath('data.result.repayments.0.payment_date', now()->subDays(2)->toDateString());
    }

    public function test_loan_application_view_passes_eligibility_snapshot_verbatim(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);
        $member = $this->member($org);
        $plan = LoanPlan::factory()->create(['organization_id' => $org->id]);

        $application = LoanApplication::factory()->submitted()->create([
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'member_id' => $member->id,
            'loan_plan_id' => $plan->id,
            'requested_amount' => 500000,
            'requested_term' => 12,
        ]);

        $application->update([
            'eligibility_checked_at' => now(),
            'eligibility_snapshot' => [
                'eligible' => true,
                'requested_amount' => 500000,
                'approved_amount' => 500000,
                'active_loan_count' => 0,
                'checks' => ['member_active' => 'pass', 'plan_active' => 'pass'],
                'failure_reasons' => [],
            ],
        ]);

        $this->actingAs($staff);

        $response = $this->postJson('/ai/tool', [
            'capability' => 'ai.loan.application.view',
            'arguments' => ['application_number' => $application->application_number],
            'question' => 'Report.',
        ]);

        $response->assertOk();
        $result = $response->json('data.result.eligibility');

        $this->assertSame($application->application_number, $response->json('data.result.application_number'));
        $this->assertTrue($result['eligible']);
        $this->assertEquals(500000.0, $result['requested_amount']);
        $this->assertEquals(500000.0, $result['approved_amount']);
        $this->assertSame(0, $result['active_loan_count']);
        $this->assertSame('pass', $result['checks']['member_active']);
    }

    public function test_loan_eligibility_matches_authoritative_service(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);
        $member = $this->member($org);
        $plan = LoanPlan::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($staff);

        $response = $this->postJson('/ai/tool', [
            'capability' => 'ai.loan.eligibility.check',
            'arguments' => [
                'loan_plan_id' => $plan->id,
                'requested_amount' => 500000,
                'term_months' => 12,
                'member_number' => $member->member_number,
            ],
            'question' => 'Can this member borrow?',
        ]);

        $response->assertOk();
        $result = $response->json('data.result');

        $expected = (new LoanEligibilityService)
            ->checkEligibility($member, $plan, 500000.0, 12);

        $this->assertSame($expected->eligible, $result['eligible']);
        $this->assertEquals($expected->approvedAmount, $result['approved_amount']);
        $this->assertSame($expected->activeLoanCount, $result['active_loan_count']);
        $this->assertSame($expected->checks, $result['checks']);
        $this->assertSame($expected->failureReasons, $result['failure_reasons']);
    }

    public function test_eligibility_is_not_rescued_by_savings_balance(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);
        $member = $this->member($org);
        $plan = LoanPlan::factory()->create(['organization_id' => $org->id]);

        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'current_balance' => 100000000,
        ]);

        $activeLoan = $this->loanFor($member, [
            'status' => \App\Enums\LoanStatus::Active,
        ]);

        $this->assertGreaterThan(0, $activeLoan->total_amount);

        $this->actingAs($staff);

        $response = $this->postJson('/ai/tool', [
            'capability' => 'ai.loan.eligibility.check',
            'arguments' => [
                'loan_plan_id' => $plan->id,
                'requested_amount' => 500000,
                'term_months' => 12,
                'member_number' => $member->member_number,
            ],
            'question' => 'Can this member borrow?',
        ]);

        $response->assertOk();
        $result = $response->json('data.result');

        $this->assertFalse($result['eligible']);
        $this->assertSame('fail', $result['checks']['active_loans_limit']);
        $this->assertNotEmpty($result['failure_reasons']);
    }

    public function test_guarantor_eligibility_matches_authoritative_service(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);

        $candidate = $this->member($org);
        $candidate->update(['national_id' => '111555444333222111']);

        $this->actingAs($staff);

        $response = $this->postJson('/ai/tool', [
            'capability' => 'ai.guarantor.eligibility.check',
            'arguments' => ['member_number' => $candidate->member_number],
            'question' => 'Can this member guarantee?',
        ]);

        $response->assertOk();
        $result = $response->json('data.result');

        $expected = (new GuarantorEligibilityService)->getEligibility($candidate);

        $this->assertSame($expected['eligible'], $result['eligible']);
        $this->assertSame($expected['reason'], $result['reason']);
        $this->assertSame($expected['active_count'], (int) $result['active_guarantees_count']);
        $this->assertSame($expected['completed_count'], (int) $result['completed_guarantees_count']);

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('111555444333222111', $payload);
    }

    public function test_collateral_requirement_matches_authoritative_service(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);
        $plan = LoanPlan::factory()->create(['organization_id' => $org->id]);

        LoanPlanCollateralRule::create([
            'loan_plan_id' => $plan->id,
            'minimum_amount' => 0,
            'maximum_amount' => 10000000,
            'collateral_required' => true,
            'coverage_percentage' => 120,
            'minimum_collateral_value' => 600000,
            'minimum_assets' => 1,
            'maximum_assets' => 3,
            'allowed_collateral_types' => ['land', 'vehicle'],
            'required_document_types' => ['title_deed', 'sale_agreement'],
            'status' => true,
            'description' => 'Business loan security requirement.',
        ]);

        $this->actingAs($staff);

        $response = $this->postJson('/ai/tool', [
            'capability' => 'ai.collateral.requirement.check',
            'arguments' => [
                'loan_plan_id' => $plan->id,
                'requested_amount' => 500000,
            ],
            'question' => 'What collateral do we need?',
        ]);

        $response->assertOk();
        $result = $response->json('data.result');

        $expected = (new CollateralRequirementService)->getRequirement($plan, 500000.0);

        $this->assertTrue($expected['required']);
        $this->assertSame($expected['required'], $result['required']);
        $this->assertEquals((float) $expected['minimum_value'], $result['minimum_value']);
        $this->assertEquals((float) $expected['coverage_percentage'], $result['coverage_percentage']);
        $this->assertSame((int) $expected['minimum_assets'], $result['minimum_assets']);
        $this->assertSame((int) $expected['maximum_assets'], $result['maximum_assets']);
        $this->assertSame($expected['allowed_types'], $result['allowed_types']);
        $this->assertSame(array_values($expected['required_documents']), $result['required_documents']);
    }

    public function test_loan_tools_scoped_to_organization(): void
    {
        $orgA = $this->makeOrganization();
        $orgB = $this->makeOrganization();

        $staffB = $this->staff($orgB);
        $memberA = $this->member($orgA);
        $loanA = $this->loanFor($memberA);

        $this->actingAs($staffB);

        $this->postJson('/ai/tool', [
            'capability' => 'ai.loan.view',
            'arguments' => ['loan_number' => $loanA->loan_number],
            'question' => 'Report.',
        ])
            ->assertStatus(403)
            ->assertJsonPath('category', 'not_found');

        $this->postJson('/ai/tool', [
            'capability' => 'ai.loan.repayments',
            'arguments' => ['loan_number' => $loanA->loan_number],
            'question' => 'Report.',
        ])
            ->assertStatus(403)
            ->assertJsonPath('category', 'not_found');
    }

    public function test_member_view_output_excludes_sensitive_pii(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);

        $member = $this->member($org);
        $member->update([
            'national_id' => 'NIDA-SECRET-992',
            'phone' => '+255700000111',
            'email' => 'private@example.com',
            'address' => 'P.O. Box 1234, Secret Street',
        ]);

        $this->actingAs($staff);

        $payload = $this->postJson('/ai/tool', $this->memberPayload('ai.member.view', $member))
            ->assertOk()
            ->assertJsonPath('data.result.member_number', $member->member_number);

        $json = json_encode($payload->json());

        $this->assertStringNotContainsString('NIDA-SECRET-992', $json);
        $this->assertStringNotContainsString('+255700000111', $json);
        $this->assertStringNotContainsString('private@example.com', $json);

        $result = $payload->json('data.result');
        $this->assertArrayHasKey('full_name', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayNotHasKey('national_id', $result);
        $this->assertArrayNotHasKey('phone', $result);
        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayNotHasKey('address', $result);
    }
}