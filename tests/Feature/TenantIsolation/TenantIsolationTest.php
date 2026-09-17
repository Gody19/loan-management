<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\WelfareAccount;
use App\Models\WelfareFund;
use App\Models\LoanPlan;
use App\Models\PaymentMethod;
use App\Models\VicobaGroup;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;
    protected Organization $orgB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $adminA;
    protected User $adminB;
    protected User $superAdmin;
    protected Member $memberA;
    protected Member $memberB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // Create two separate organizations
        $this->orgA = Organization::factory()->create(['status' => 'active']);
        $this->orgB = Organization::factory()->create(['status' => 'active']);

        $this->branchA = Branch::factory()->create(['organization_id' => $this->orgA->id]);
        $this->branchB = Branch::factory()->create(['organization_id' => $this->orgB->id]);

        // Org A admin
        $this->adminA = User::factory()->create(['password' => bcrypt('password')]);
        $this->adminA->assignRole('Organization Administrator');
        $this->adminA->organizations()->attach($this->orgA->id);
        $this->adminA->branches()->attach($this->branchA->id);

        // Org B admin
        $this->adminB = User::factory()->create(['password' => bcrypt('password')]);
        $this->adminB->assignRole('Organization Administrator');
        $this->adminB->organizations()->attach($this->orgB->id);
        $this->adminB->branches()->attach($this->branchB->id);

        // Super admin
        $this->superAdmin = User::factory()->create(['password' => bcrypt('password')]);
        $this->superAdmin->assignRole('Super Administrator');

        // Members in each org
        $this->memberA = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
        ]);
        $this->memberB = Member::factory()->create([
            'organization_id' => $this->orgB->id,
            'branch_id' => $this->branchB->id,
        ]);
    }

    // ===== Organization Context Tests =====

    public function test_org_a_admin_cannot_access_org_b_organization(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->get(route('organizations.show', $this->orgB));
        $response->assertStatus(403);
    }

    public function test_org_b_admin_cannot_access_org_a_organization(): void
    {
        $this->actingAs($this->adminB);
        $response = $this->get(route('organizations.show', $this->orgA));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_all_organizations(): void
    {
        $this->actingAs($this->superAdmin);
        $response = $this->get(route('organizations.show', $this->orgA));
        $response->assertStatus(200);

        $response = $this->get(route('organizations.show', $this->orgB));
        $response->assertStatus(200);
    }

    // ===== Branch Tenant Isolation Tests =====

    public function test_org_a_admin_cannot_view_org_b_branch(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->get(route('branches.show', $this->branchB));
        $response->assertStatus(403);
    }

    public function test_org_b_admin_cannot_view_org_a_branch(): void
    {
        $this->actingAs($this->adminB);
        $response = $this->get(route('branches.show', $this->branchA));
        $response->assertStatus(403);
    }

    public function test_org_a_admin_branch_index_scoped(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->get(route('branches.index'));
        $response->assertStatus(200);
        $response->assertDontSee($this->branchB->name);
    }

    // ===== Member Tenant Isolation Tests =====

    public function test_org_a_admin_cannot_view_org_b_member(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->get(route('members.show', $this->memberB));
        $response->assertStatus(403);
    }

    public function test_org_b_admin_cannot_view_org_a_member(): void
    {
        $this->actingAs($this->adminB);
        $response = $this->get(route('members.show', $this->memberA));
        $response->assertStatus(403);
    }

    public function test_org_a_admin_member_index_scoped(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->get(route('members.index'));
        $response->assertStatus(200);
    }

    // ===== Savings Account Tenant Isolation Tests =====

    public function test_org_a_savings_account_not_visible_to_org_b(): void
    {
        $savingsProduct = SavingsProduct::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);
        $accountA = SavingsAccount::factory()->create([
            'member_id' => $this->memberA->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'savings_product_id' => $savingsProduct->id,
        ]);

        $this->actingAs($this->adminB);
        $response = $this->get(route('savings-accounts.show', $accountA));
        $response->assertStatus(403);
    }

    public function test_org_a_savings_account_visible_to_org_a(): void
    {
        $savingsProduct = SavingsProduct::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);
        $accountA = SavingsAccount::factory()->create([
            'member_id' => $this->memberA->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'savings_product_id' => $savingsProduct->id,
        ]);

        $this->actingAs($this->adminA);
        $response = $this->get(route('savings-accounts.show', $accountA));
        $response->assertStatus(200);
    }

    // ===== Share Account Tenant Isolation Tests =====

    public function test_org_a_share_account_not_visible_to_org_b(): void
    {
        $shareProduct = ShareProduct::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);
        $accountA = ShareAccount::factory()->create([
            'member_id' => $this->memberA->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'share_product_id' => $shareProduct->id,
        ]);

        $this->actingAs($this->adminB);
        $response = $this->get(route('share-accounts.show', $accountA));
        $response->assertStatus(403);
    }

    // ===== Welfare Account Tenant Isolation Tests =====

    public function test_org_a_welfare_account_not_visible_to_org_b(): void
    {
        $welfareFund = WelfareFund::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);
        $accountA = WelfareAccount::factory()->create([
            'member_id' => $this->memberA->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'welfare_fund_id' => $welfareFund->id,
        ]);

        $this->actingAs($this->adminB);
        $response = $this->get(route('welfare-accounts.show', $accountA));
        $response->assertStatus(403);
    }

    // ===== Loan Plan Tenant Isolation Tests =====

    public function test_org_a_loan_plan_not_visible_to_org_b(): void
    {
        $planA = LoanPlan::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->adminB);
        $response = $this->get(route('loan-plans.show', $planA));
        $response->assertStatus(403);
    }

    // ===== Payment Method Tenant Isolation Tests =====

    public function test_org_a_payment_method_not_visible_to_org_b(): void
    {
        $methodA = PaymentMethod::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->adminB);
        $response = $this->get(route('payment-methods.show', $methodA));
        $response->assertStatus(403);
    }

    // ===== Savings Product Tenant Isolation Tests =====

    public function test_org_a_savings_product_not_visible_to_org_b(): void
    {
        $productA = SavingsProduct::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->adminB);
        $response = $this->get(route('savings-products.show', $productA));
        $response->assertStatus(403);
    }

    // ===== Share Product Tenant Isolation Tests =====

    public function test_org_a_share_product_not_visible_to_org_b(): void
    {
        $productA = ShareProduct::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->adminB);
        $response = $this->get(route('share-products.show', $productA));
        $response->assertStatus(403);
    }

    // ===== Welfare Fund Tenant Isolation Tests =====

    public function test_org_a_welfare_fund_not_visible_to_org_b(): void
    {
        $fundA = WelfareFund::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->adminB);
        $response = $this->get(route('welfare-funds.show', $fundA));
        $response->assertStatus(403);
    }

    // ===== Store Method Validation Tests =====

    public function test_org_a_admin_cannot_create_branch_in_org_b(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->post(route('branches.store'), [
            'organization_id' => $this->orgB->id,
            'code' => 'BR-X99',
            'name' => 'Unauthorized Branch',
            'status' => 'active',
        ]);
        $response->assertStatus(403);
    }

    public function test_org_a_admin_cannot_create_loan_plan_in_org_b(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->post(route('loan-plans.store'), [
            'organization_id' => $this->orgB->id,
            'name' => 'Unauthorized Plan',
            'code' => 'LP-X99',
            'loan_purpose' => 'business',
            'interest_rate' => 12.0,
            'interest_method' => 'flat',
            'minimum_amount' => 10000,
            'maximum_amount' => 1000000,
            'minimum_term' => 1,
            'maximum_term' => 12,
            'repayment_frequency' => 'monthly',
            'maximum_active_loans' => 3,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 2,
            'share_multiplier' => 2,
            'maximum_loan_to_savings_ratio' => 0.8,
            'grace_period' => 0,
            'processing_fee' => 0,
            'insurance_fee' => 0,
            'status' => 'active',
        ]);
        $response->assertStatus(403);
    }

    public function test_org_a_admin_cannot_create_payment_method_in_org_b(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->post(route('payment-methods.store'), [
            'organization_id' => $this->orgB->id,
            'name' => 'Unauthorized Payment',
            'code' => 'PAY-X99',
            'type' => 'cash',
            'status' => 'active',
        ]);
        $response->assertStatus(403);
    }

    public function test_org_a_admin_cannot_create_savings_product_in_org_b(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->post(route('savings-products.store'), [
            'organization_id' => $this->orgB->id,
            'name' => 'Unauthorized Savings',
            'code' => 'SV-X99',
            'interest_rate' => 5.0,
            'minimum_balance' => 1000,
            'status' => 'active',
        ]);
        $response->assertStatus(403);
    }

    public function test_org_a_admin_cannot_create_share_product_in_org_b(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->post(route('share-products.store'), [
            'organization_id' => $this->orgB->id,
            'name' => 'Unauthorized Share',
            'code' => 'SH-X99',
            'minimum_shares' => 1,
            'maximum_shares' => 1000,
            'share_value' => 10000,
            'status' => 'active',
        ]);
        $response->assertStatus(403);
    }

    public function test_org_a_admin_cannot_create_welfare_fund_in_org_b(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->post(route('welfare-funds.store'), [
            'organization_id' => $this->orgB->id,
            'name' => 'Unauthorized Fund',
            'code' => 'WF-X99',
            'contribution_amount' => 5000,
            'contribution_frequency' => 'monthly',
            'status' => 'active',
        ]);
        $response->assertStatus(403);
    }

    // ===== Super Admin Bypass Tests =====

    public function test_super_admin_can_access_all_resources(): void
    {
        $this->actingAs($this->superAdmin);

        $this->get(route('organizations.show', $this->orgA))->assertStatus(200);
        $this->get(route('organizations.show', $this->orgB))->assertStatus(200);
        $this->get(route('branches.show', $this->branchA))->assertStatus(200);
        $this->get(route('branches.show', $this->branchB))->assertStatus(200);
        $this->get(route('members.show', $this->memberA))->assertStatus(200);
        $this->get(route('members.show', $this->memberB))->assertStatus(200);
    }

    // ===== Index Scoped Queries Tests =====

    public function test_org_a_users_index_only_shows_org_a_users(): void
    {
        $this->actingAs($this->adminA);
        $response = $this->get(route('users.index'));
        $response->assertStatus(200);
    }

    public function test_super_admin_users_index_shows_all(): void
    {
        $this->actingAs($this->superAdmin);
        $response = $this->get(route('users.index'));
        $response->assertStatus(200);
    }
}
