<?php

namespace Tests\Feature\Finance;

use App\Enums\LoanPlanStatus;
use App\Enums\MemberStatus;
use App\Models\Branch;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\Organization;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
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

    // ─────────────────────────────────────────────
    // Eligibility service tests
    // ─────────────────────────────────────────────

    public function test_active_member_with_savings_is_eligible(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id]);
        SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-ELIG-001',
            'current_balance' => 500000,
            'opening_date' => now(),
            'status' => 'active',
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'minimum_savings_balance' => 100000,
            'savings_multiplier' => 3,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);
        $result = $service->checkEligibility($member, $plan, 500000);

        $this->assertTrue($result->eligible);
        $this->assertGreaterThan(0, $result->passCount());
    }

    public function test_inactive_member_is_not_eligible(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Pending,
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);
        $result = $service->checkEligibility($member, $plan, 100000);

        $this->assertFalse($result->eligible);
        $this->assertContains('Member is not active.', $result->failureReasons);
    }

    public function test_inactive_plan_is_not_eligible(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => LoanPlanStatus::Inactive,
        ]);

        $service = app(LoanEligibilityService::class);
        $result = $service->checkEligibility($member, $plan, 100000);

        $this->assertFalse($result->eligible);
        $this->assertContains('Loan plan is not active.', $result->failureReasons);
    }

    public function test_amount_outside_range_is_not_eligible(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 100000,
            'maximum_amount' => 5000000,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);

        // Below minimum
        $resultBelow = $service->checkEligibility($member, $plan, 50000);
        $this->assertFalse($resultBelow->eligible);
        $this->assertStringContainsString('Requested amount is outside the allowed range', $resultBelow->failureReasons[0]);

        // Above maximum
        $resultAbove = $service->checkEligibility($member, $plan, 10000000);
        $this->assertFalse($resultAbove->eligible);
    }

    public function test_savings_multiplier_limit_enforced(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id]);
        SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-ELIG-002',
            'current_balance' => 100000,
            'opening_date' => now(),
            'status' => 'active',
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 3,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 100,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);

        // 100000 savings × 3 = 300000, capped at 80% = 240000
        // Requesting 240000 should pass
        $resultPass = $service->checkEligibility($member, $plan, 240000);
        $this->assertTrue($resultPass->eligible);

        // Requesting 241000 should fail (exceeds 240000 limit)
        $resultFail = $service->checkEligibility($member, $plan, 241000);
        $this->assertFalse($resultFail->eligible);
    }

    public function test_eighty_percent_rule_applied(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id]);
        SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-ELIG-80',
            'current_balance' => 1000000,
            'opening_date' => now(),
            'status' => 'active',
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 50000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 5,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 100,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);

        // 1000000 × 5 = 5000000, 80% = 4000000
        // Requesting 4000000 should pass
        $result = $service->checkEligibility($member, $plan, 4000000);
        $this->assertTrue($result->eligible);

        // Requesting 4000001 should fail
        $result2 = $service->checkEligibility($member, $plan, 4000001);
        $this->assertFalse($result2->eligible);
    }

    public function test_minimum_savings_balance_enforced(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id]);
        SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-ELIG-MIN',
            'current_balance' => 50000,
            'opening_date' => now(),
            'status' => 'active',
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_savings_balance' => 100000,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);
        $result = $service->checkEligibility($member, $plan, 50000);

        $this->assertFalse($result->eligible);
        $this->assertStringContainsString('Minimum savings balance', $result->failureReasons[0]);
    }

    public function test_eligibility_check_result_has_correct_fields(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => LoanPlanStatus::Active,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 3,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 100,
        ]);

        $service = app(LoanEligibilityService::class);
        $result = $service->checkEligibility($member, $plan, 200000);

        $this->assertEquals($member->full_name, $result->memberName);
        $this->assertEquals($plan->name, $result->planName);
        $this->assertEquals(200000, $result->requestedAmount);
        $this->assertIsArray($result->checks);
        $this->assertIsArray($result->failureReasons);
        $this->assertIsFloat($result->totalSavings);
        $this->assertIsFloat($result->totalShares);
        $this->assertIsInt($result->activeLoanCount);
        $this->assertIsFloat($result->maxAllowedBySavings);
        $this->assertIsFloat($result->maxAllowedByShares);
    }

    public function test_result_pass_count_and_fail_count_work(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => LoanPlanStatus::Active,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 3,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 100,
        ]);

        $service = app(LoanEligibilityService::class);
        $result = $service->checkEligibility($member, $plan, 200000);

        $this->assertEquals($result->passCount() + $result->failCount(), $result->totalChecks());
    }

    // ─────────────────────────────────────────────
    // Share multiplier formula tests
    // ─────────────────────────────────────────────

    public function test_share_multiplier_enforced_when_member_has_shares(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id]);
        SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-ELIG-SH1',
            'current_balance' => 5000000,
            'opening_date' => now(),
            'status' => 'active',
        ]);

        $shareProduct = ShareProduct::factory()->create(['organization_id' => $this->orgA->id, 'share_price' => 10000]);
        ShareAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'share_product_id' => $shareProduct->id,
            'account_number' => 'SHR-ELIG-001',
            'total_shares' => 50,
            'total_value' => 500000,
            'status' => 'active',
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 50000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 10,
            'share_multiplier' => 2,
            'maximum_loan_to_savings_ratio' => 100,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);

        // Shares = 500,000, share_multiplier = 2, 80% rule
        // maxLoanFromShares = 500,000 × 2 × 0.8 = 800,000
        // Requesting 800,000 should pass share check
        $resultPass = $service->checkEligibility($member, $plan, 800000);
        $this->assertTrue($resultPass->eligible);
        $this->assertEquals('pass', $resultPass->checks['share_multiplier']);

        // Requesting 800,001 should fail share check
        $resultFail = $service->checkEligibility($member, $plan, 800001);
        $this->assertFalse($resultFail->eligible);
        $this->assertEquals('fail', $resultFail->checks['share_multiplier']);
    }

    public function test_share_multiplier_bypassed_when_member_has_no_shares(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 50000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 3,
            'share_multiplier' => 5,
            'maximum_loan_to_savings_ratio' => 100,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);

        // No savings, no shares, requesting 1,000,000
        // share_multiplier check should auto-pass (no shares)
        // savings_multiplier: maxLoanFromSavings = 0 × 3 × 0.8 = 0; 1,000,000 > 0 → FAIL
        // loan_to_savings_ratio: 0 savings → FAIL
        $result = $service->checkEligibility($member, $plan, 1000000);

        $this->assertEquals('pass', $result->checks['share_multiplier']);
        $this->assertEquals(0, $result->totalShares);
        $this->assertEquals(0, $result->maxAllowedByShares);
    }

    public function test_share_multiplier_fail_blocks_eligibility(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id]);
        SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-ELIG-SH2',
            'current_balance' => 5000000,
            'opening_date' => now(),
            'status' => 'active',
        ]);

        $shareProduct = ShareProduct::factory()->create(['organization_id' => $this->orgA->id, 'share_price' => 10000]);
        ShareAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'share_product_id' => $shareProduct->id,
            'account_number' => 'SHR-ELIG-002',
            'total_shares' => 10,
            'total_value' => 100000,
            'status' => 'active',
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 50000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 10,
            'share_multiplier' => 2,
            'maximum_loan_to_savings_ratio' => 100,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);

        // Savings = 5,000,000, savings_multiplier = 10, 80% → maxLoanFromSavings = 40,000,000
        // Shares = 100,000, share_multiplier = 2, 80% → maxLoanFromShares = 160,000
        // Requesting 1,000,000: savings passes, shares fails → NOT eligible
        $result = $service->checkEligibility($member, $plan, 1000000);

        $this->assertFalse($result->eligible);
        $this->assertEquals('pass', $result->checks['savings_multiplier']);
        $this->assertEquals('fail', $result->checks['share_multiplier']);
        $this->assertEquals(160000, $result->maxAllowedByShares);
    }

    // ─────────────────────────────────────────────
    // Loan-to-savings ratio formula tests
    // ─────────────────────────────────────────────

    public function test_loan_to_savings_ratio_enforced(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id]);
        SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-ELIG-RATIO',
            'current_balance' => 1000000,
            'opening_date' => now(),
            'status' => 'active',
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 50000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 10,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 2,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);

        // Savings = 1,000,000, ratio limit = 2
        // maxLoanFromSavings = 1,000,000 × 10 × 0.8 = 8,000,000
        // Requesting 2,000,000: ratio = 2.0 <= 2 → PASS
        $resultPass = $service->checkEligibility($member, $plan, 2000000);
        $this->assertTrue($resultPass->eligible);
        $this->assertEquals('pass', $resultPass->checks['loan_to_savings_ratio']);

        // Requesting 2,000,001: ratio = 2.000001 > 2 → FAIL
        $resultFail = $service->checkEligibility($member, $plan, 2000001);
        $this->assertFalse($resultFail->eligible);
        $this->assertEquals('fail', $resultFail->checks['loan_to_savings_ratio']);
    }

    public function test_zero_savings_fails_loan_to_savings_ratio(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 3,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 5,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);

        // No savings, requesting 1 → ratio check should fail
        $result = $service->checkEligibility($member, $plan, 1);
        $this->assertFalse($result->eligible);
        $this->assertEquals('fail', $result->checks['loan_to_savings_ratio']);
        $this->assertContains('Cannot borrow with zero savings balance.', $result->failureReasons);
    }

    // ─────────────────────────────────────────────
    // Combined rules tests
    // ─────────────────────────────────────────────

    public function test_savings_is_binding_constraint_when_lower_than_shares(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id]);
        SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-ELIG-CMB1',
            'current_balance' => 200000,
            'opening_date' => now(),
            'status' => 'active',
        ]);

        $shareProduct = ShareProduct::factory()->create(['organization_id' => $this->orgA->id, 'share_price' => 10000]);
        ShareAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'share_product_id' => $shareProduct->id,
            'account_number' => 'SHR-ELIG-CMB1',
            'total_shares' => 100,
            'total_value' => 1000000,
            'status' => 'active',
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 50000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 3,
            'share_multiplier' => 2,
            'maximum_loan_to_savings_ratio' => 100,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);

        // Savings = 200,000, savings_mult = 3, 80% → maxLoanFromSavings = 480,000
        // Shares = 1,000,000, share_mult = 2, 80% → maxLoanFromShares = 1,600,000
        // Requesting 500,000: savings check FAILS (500,000 > 480,000), shares passes
        $result = $service->checkEligibility($member, $plan, 500000);

        $this->assertFalse($result->eligible);
        $this->assertEquals('fail', $result->checks['savings_multiplier']);
        $this->assertEquals('pass', $result->checks['share_multiplier']);
        $this->assertEquals(480000, $result->maxAllowedBySavings);
    }

    public function test_shares_is_binding_constraint_when_lower_than_savings(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id]);
        SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-ELIG-CMB2',
            'current_balance' => 5000000,
            'opening_date' => now(),
            'status' => 'active',
        ]);

        $shareProduct = ShareProduct::factory()->create(['organization_id' => $this->orgA->id, 'share_price' => 10000]);
        ShareAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'share_product_id' => $shareProduct->id,
            'account_number' => 'SHR-ELIG-CMB2',
            'total_shares' => 50,
            'total_value' => 500000,
            'status' => 'active',
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 50000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 10,
            'share_multiplier' => 2,
            'maximum_loan_to_savings_ratio' => 100,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);

        // Savings = 5,000,000, savings_mult = 10, 80% → maxLoanFromSavings = 40,000,000
        // Shares = 500,000, share_mult = 2, 80% → maxLoanFromShares = 800,000
        // Requesting 1,000,000: savings passes, shares FAILS
        $result = $service->checkEligibility($member, $plan, 1000000);

        $this->assertFalse($result->eligible);
        $this->assertEquals('pass', $result->checks['savings_multiplier']);
        $this->assertEquals('fail', $result->checks['share_multiplier']);
        $this->assertEquals(800000, $result->maxAllowedByShares);
    }

    public function test_worked_example_savings_1m_shares_500k(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id]);
        SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-ELIG-WRK',
            'current_balance' => 1000000,
            'opening_date' => now(),
            'status' => 'active',
        ]);

        $shareProduct = ShareProduct::factory()->create(['organization_id' => $this->orgA->id, 'share_price' => 10000]);
        ShareAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'share_product_id' => $shareProduct->id,
            'account_number' => 'SHR-ELIG-WRK',
            'total_shares' => 50,
            'total_value' => 500000,
            'status' => 'active',
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 50000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 3,
            'share_multiplier' => 2,
            'maximum_loan_to_savings_ratio' => 100,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);

        // Savings = 1,000,000, savings_mult = 3, 80% → maxLoanFromSavings = 2,400,000
        // Shares = 500,000, share_mult = 2, 80% → maxLoanFromShares = 800,000
        // Requesting 2,000,000: savings passes (2,000,000 <= 2,400,000)
        //                       shares FAILS (2,000,000 > 800,000)
        //                       → NOT ELIGIBLE
        $result = $service->checkEligibility($member, $plan, 2000000);

        $this->assertFalse($result->eligible);
        $this->assertEquals('pass', $result->checks['savings_multiplier']);
        $this->assertEquals('fail', $result->checks['share_multiplier']);
        $this->assertEquals(2400000, $result->maxAllowedBySavings);
        $this->assertEquals(800000, $result->maxAllowedByShares);
        $this->assertEquals(1000000, $result->totalSavings);
        $this->assertEquals(500000, $result->totalShares);

        // Now request 800,000: all checks should pass
        $resultPass = $service->checkEligibility($member, $plan, 800000);

        $this->assertTrue($resultPass->eligible);
        $this->assertEquals(800000, $resultPass->approvedAmount);
    }

    public function test_approved_amount_is_zero_when_not_eligible(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Pending,
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);
        $result = $service->checkEligibility($member, $plan, 500000);

        $this->assertFalse($result->eligible);
        $this->assertEquals(0, $result->approvedAmount);
    }

    public function test_approved_amount_equals_requested_when_eligible(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id]);
        SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-ELIG-APPR',
            'current_balance' => 500000,
            'opening_date' => now(),
            'status' => 'active',
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 3,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 100,
            'status' => LoanPlanStatus::Active,
        ]);

        // Savings = 500,000, savings_mult = 3, 80% → maxLoanFromSavings = 1,200,000
        // Requesting 200,000 → all checks pass → approvedAmount = 200,000
        $service = app(LoanEligibilityService::class);
        $result = $service->checkEligibility($member, $plan, 200000);

        $this->assertTrue($result->eligible);
        $this->assertEquals(200000, $result->approvedAmount);
    }

    // ─────────────────────────────────────────────
    // Active loan limit tests (placeholder)
    // ─────────────────────────────────────────────

    public function test_active_loan_limit_always_passes_when_no_loans(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'minimum_savings_balance' => 0,
            'maximum_active_loans' => 1,
            'savings_multiplier' => 3,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 100,
            'status' => LoanPlanStatus::Active,
        ]);

        $service = app(LoanEligibilityService::class);
        $result = $service->checkEligibility($member, $plan, 100000);

        $this->assertEquals('pass', $result->checks['active_loans_limit']);
        $this->assertEquals(0, $result->activeLoanCount);
    }

    // ─────────────────────────────────────────────
    // Controller / route tests
    // ─────────────────────────────────────────────

    public function test_eligibility_index_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get(route('loan-eligibility.index'));

        $response->assertStatus(200);
        $response->assertViewIs('loan-eligibility.index');
    }

    public function test_eligibility_check_route_works(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => LoanPlanStatus::Active,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-eligibility.check'), [
            'member_id' => $member->id,
            'loan_plan_id' => $plan->id,
            'requested_amount' => 200000,
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('loan-eligibility.result');
    }

    public function test_eligibility_check_requires_valid_member(): void
    {
        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => LoanPlanStatus::Active,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-eligibility.check'), [
            'member_id' => 99999,
            'loan_plan_id' => $plan->id,
            'requested_amount' => 200000,
        ]);

        $response->assertSessionHasErrors('member_id');
    }

    public function test_eligibility_check_requires_valid_plan(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-eligibility.check'), [
            'member_id' => $member->id,
            'loan_plan_id' => 99999,
            'requested_amount' => 200000,
        ]);

        $response->assertSessionHasErrors('loan_plan_id');
    }

    public function test_eligibility_check_requires_requested_amount(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
        ]);

        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-eligibility.check'), [
            'member_id' => $member->id,
            'loan_plan_id' => $plan->id,
        ]);

        $response->assertSessionHasErrors('requested_amount');
    }

    // ─────────────────────────────────────────────
    // Security tests
    // ─────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_loan_plans(): void
    {
        $response = $this->get(route('loan-plans.index'));

        $response->assertRedirect('/login');
    }

    public function test_unauthenticated_user_cannot_access_eligibility(): void
    {
        $response = $this->get(route('loan-eligibility.index'));

        $response->assertRedirect('/login');
    }

    public function test_code_uniqueness_is_per_organization(): void
    {
        $org1 = $this->orgA;
        $org2 = $this->orgB;

        LoanPlan::factory()->create([
            'organization_id' => $org1->id,
            'code' => 'LPN-SHARED',
        ]);

        // Same code in different org should succeed
        $response = $this->actingAs($this->admin)->post(route('loan-plans.store'), [
            'name' => 'Shared Code Plan',
            'code' => 'LPN-SHARED',
            'organization_id' => $org2->id,
            'loan_purpose' => 'business',
            'minimum_amount' => 100000,
            'maximum_amount' => 10000000,
            'interest_rate' => 2.5,
            'interest_method' => 'flat',
            'minimum_term' => 3,
            'maximum_term' => 36,
            'repayment_frequency' => 'monthly',
            'maximum_active_loans' => 1,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 3,
            'share_multiplier' => 2,
            'maximum_loan_to_savings_ratio' => 5,
            'grace_period' => 0,
            'processing_fee' => 0,
            'insurance_fee' => 0,
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_plans', [
            'code' => 'LPN-SHARED',
            'organization_id' => $org2->id,
        ]);
    }
}
