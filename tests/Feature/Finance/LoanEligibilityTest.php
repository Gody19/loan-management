<?php

namespace Tests\Feature\Finance;

use App\Enums\LoanPlanStatus;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Services\LoanEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $orgA;
    private Organization $orgB;
    private Branch $branch;
    private VicobaGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();
        $this->branch = Branch::factory()->create(['organization_id' => $this->orgA->id]);
        $this->group = VicobaGroup::factory()->create(['branch_id' => $this->branch->id]);
    }

    private function makePlan(array $overrides = []): LoanPlan
    {
        return LoanPlan::create(array_merge([
            'organization_id' => $this->orgA->id,
            'name' => 'Test Plan',
            'code' => 'TST-' . uniqid(),
            'loan_purpose' => 'personal',
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'interest_rate' => 10,
            'interest_method' => 'flat',
            'minimum_term' => 3,
            'maximum_term' => 24,
            'repayment_frequency' => 'monthly',
            'maximum_active_loans' => 1,
            'requires_guarantor' => false,
            'minimum_guarantors' => 0,
            'requires_collateral' => false,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 3,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 5,
            'grace_period' => 5,
            'processing_fee' => 1,
            'insurance_fee' => 0.5,
            'late_payment_allowed' => true,
            'status' => 'active',
        ], $overrides));
    }

    private function makeMember(array $overrides = []): Member
    {
        $user = User::factory()->create(['is_active' => true, 'status' => 'active']);
        return Member::factory()->create(array_merge([
            'user_id' => $user->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ], $overrides));
    }

    // ─────────────────────────────────────────────
    // Basic eligibility
    // ─────────────────────────────────────────────

    public function test_active_member_is_eligible(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan();

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 500000);

        $this->assertTrue($result->eligible);
        $this->assertEmpty($result->failureReasons);
    }

    public function test_inactive_member_is_not_eligible(): void
    {
        $member = $this->makeMember(['membership_status' => MemberStatus::Inactive]);
        $plan = $this->makePlan();

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 500000);

        $this->assertFalse($result->eligible);
        $this->assertContains('Member is not active.', $result->failureReasons);
    }

    public function test_inactive_plan_is_not_eligible(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan(['status' => 'inactive']);

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 500000);

        $this->assertFalse($result->eligible);
        $this->assertContains('Loan plan is not active.', $result->failureReasons);
    }

    public function test_amount_out_of_range_is_not_eligible(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan(['minimum_amount' => 100000, 'maximum_amount' => 1000000]);

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 50000);

        $this->assertFalse($result->eligible);
        $this->assertStringContainsString('outside the allowed range', $result->failureReasons[0]);
    }

    public function test_term_out_of_range_is_not_eligible(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan(['minimum_term' => 3, 'maximum_term' => 12]);

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 500000, 24);

        $this->assertFalse($result->eligible);
        $this->assertStringContainsString('outside the allowed range', $result->failureReasons[0]);
    }

    // ─────────────────────────────────────────────
    // Active loans & 85% rule
    // ─────────────────────────────────────────────

    public function test_no_active_loans_is_eligible(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan();

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 500000);

        $this->assertTrue($result->eligible);
        $this->assertEquals(0, $result->activeLoanCount);
    }

    public function test_active_loan_paid_less_than_85_percent_blocks_new_loan(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan();

        Loan::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'member_id' => $member->id,
            'loan_plan_id' => $plan->id,
            'loan_number' => 'LN-TEST-001',
            'principal_amount' => 1000000,
            'disbursed_amount' => 1000000,
            'interest_rate' => 10,
            'interest_method' => 'flat',
            'term_months' => 12,
            'repayment_frequency' => 'monthly',
            'total_interest' => 100000,
            'total_amount' => 1100000,
            'amount_paid' => 300000,
            'outstanding_balance' => 800000,
            'status' => LoanStatus::Active,
            'disbursement_date' => now()->subMonths(3),
            'maturity_date' => now()->addMonths(9),
            'installments_paid' => 3,
            'total_installments' => 12,
        ]);

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 500000);

        $this->assertFalse($result->eligible);
        $this->assertArrayHasKey('active_loans_limit', $result->checks);
        $this->assertEquals('fail', $result->checks['active_loans_limit']);
        $this->assertStringContainsString('85%', $result->failureReasons[0]);
    }

    public function test_active_loan_paid_85_percent_or_more_allows_new_loan(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan(['maximum_active_loans' => 2]);

        Loan::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'member_id' => $member->id,
            'loan_plan_id' => $plan->id,
            'loan_number' => 'LN-TEST-002',
            'principal_amount' => 1000000,
            'disbursed_amount' => 1000000,
            'interest_rate' => 10,
            'interest_method' => 'flat',
            'term_months' => 12,
            'repayment_frequency' => 'monthly',
            'total_interest' => 100000,
            'total_amount' => 1100000,
            'amount_paid' => 1000000,
            'outstanding_balance' => 100000,
            'status' => LoanStatus::Active,
            'disbursement_date' => now()->subMonths(10),
            'maturity_date' => now()->addMonths(2),
            'installments_paid' => 10,
            'total_installments' => 12,
        ]);

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 500000);

        $this->assertTrue($result->eligible);
    }

    public function test_maximum_active_loans_enforced_when_all_paid(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan(['maximum_active_loans' => 1]);

        Loan::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'member_id' => $member->id,
            'loan_plan_id' => $plan->id,
            'loan_number' => 'LN-TEST-003',
            'principal_amount' => 1000000,
            'disbursed_amount' => 1000000,
            'interest_rate' => 10,
            'interest_method' => 'flat',
            'term_months' => 12,
            'repayment_frequency' => 'monthly',
            'total_interest' => 100000,
            'total_amount' => 1100000,
            'amount_paid' => 1100000,
            'outstanding_balance' => 0,
            'status' => LoanStatus::Active,
            'disbursement_date' => now()->subMonths(11),
            'maturity_date' => now()->addMonth(),
            'installments_paid' => 11,
            'total_installments' => 12,
        ]);

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 500000);

        $this->assertFalse($result->eligible);
        $this->assertEquals('fail', $result->checks['active_loans_limit']);
        $this->assertStringContainsString('maximum number of active loans', $result->failureReasons[0]);
    }

    public function test_completed_loan_does_not_count(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan(['maximum_active_loans' => 1]);

        Loan::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'member_id' => $member->id,
            'loan_plan_id' => $plan->id,
            'loan_number' => 'LN-TEST-004',
            'principal_amount' => 1000000,
            'disbursed_amount' => 1000000,
            'interest_rate' => 10,
            'interest_method' => 'flat',
            'term_months' => 12,
            'repayment_frequency' => 'monthly',
            'total_interest' => 100000,
            'total_amount' => 1100000,
            'amount_paid' => 1100000,
            'outstanding_balance' => 0,
            'status' => LoanStatus::Completed,
            'disbursement_date' => now()->subYear(),
            'maturity_date' => now()->subMonths(1),
            'installments_paid' => 12,
            'total_installments' => 12,
        ]);

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 500000);

        $this->assertTrue($result->eligible);
        $this->assertEquals(0, $result->activeLoanCount);
    }

    // ─────────────────────────────────────────────
    // Multiple failures
    // ─────────────────────────────────────────────

    public function test_multiple_failures_all_reported(): void
    {
        $member = $this->makeMember(['membership_status' => MemberStatus::Inactive]);
        $plan = $this->makePlan(['minimum_amount' => 100000]);

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 50000);

        $this->assertFalse($result->eligible);
        $this->assertGreaterThanOrEqual(2, count($result->failureReasons));
    }

    // ─────────────────────────────────────────────
    // DTO
    // ─────────────────────────────────────────────

    public function test_result_dto_returns_correct_counts(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan();

        $result = app(LoanEligibilityService::class)->checkEligibility($member, $plan, 500000);

        $this->assertTrue($result->eligible);
        $this->assertGreaterThan(0, $result->passCount());
        $this->assertEquals(0, $result->failCount());
        $this->assertEquals($result->passCount(), $result->totalChecks());
    }
}
