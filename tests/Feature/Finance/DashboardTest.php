<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareFund;
use App\Models\WelfareTransaction;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
    }

    // =========================================================================
    // AUTHENTICATION
    // =========================================================================

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertViewIs('dashboard.index');
    }

    // =========================================================================
    // SUPER ADMIN DASHBOARD
    // =========================================================================

    public function test_super_admin_sees_platform_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertViewHas('dashboard_type', 'super_admin');
    }

    public function test_super_admin_sees_organization_count(): void
    {
        Organization::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertViewHas('widgets', function ($widgets) {
            return $widgets['organizations']['total'] === 3;
        });
    }

    public function test_super_admin_sees_branch_count(): void
    {
        $org = Organization::factory()->create();
        Branch::factory()->count(2)->create(['organization_id' => $org->id]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            return $widgets['branches']['total'] === 2;
        });
    }

    public function test_super_admin_sees_user_count(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            return $widgets['users']['total'] >= 1;
        });
    }

    public function test_super_admin_sees_loan_stats(): void
    {
        $plans = LoanPlan::factory()->count(2)->create();
        $org = $plans->first()->organization_id;
        LoanApplication::factory()->submitted()->count(3)->create([
            'organization_id' => $org,
            'loan_plan_id' => $plans->first()->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) use ($plans) {
            return $widgets['loans']['pending'] === 3
                && $widgets['loans']['plans'] === $plans->count();
        });
    }

    // =========================================================================
    // ORGANIZATION ADMIN DASHBOARD
    // =========================================================================

    public function test_org_admin_sees_organization_dashboard(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Organization Administrator');
        $org->users()->attach($user->id);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertViewHas('dashboard_type', 'org_admin');
    }

    public function test_org_admin_sees_only_own_organization_stats(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $userA = User::factory()->create(['status' => 'active']);
        $userA->assignRole('Organization Administrator');
        $orgA->users()->attach($userA->id);

        // Create members in both orgs
        Member::factory()->count(3)->create(['organization_id' => $orgA->id]);
        Member::factory()->count(5)->create(['organization_id' => $orgB->id]);

        $response = $this->actingAs($userA)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            return $widgets['members']['total'] === 3;
        });
    }

    public function test_org_admin_cannot_see_other_org_data(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $userA = User::factory()->create(['status' => 'active']);
        $userA->assignRole('Organization Administrator');
        $orgA->users()->attach($userA->id);

        Member::factory()->count(5)->create(['organization_id' => $orgB->id]);
        $branchB = Branch::factory()->create(['organization_id' => $orgB->id]);
        VicobaGroup::factory()->count(3)->create(['branch_id' => $branchB->id]);

        $response = $this->actingAs($userA)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            return $widgets['members']['total'] === 0
                && $widgets['organization']['branches'] === 0
                && $widgets['organization']['groups'] === 0;
        });
    }

    public function test_org_admin_sees_financial_stats_scoped(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Organization Administrator');
        $org->users()->attach($user->id);

        $branch = Branch::factory()->create(['organization_id' => $org->id]);
        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $org->id]);
        $member = Member::factory()->create(['organization_id' => $org->id, 'branch_id' => $branch->id]);
        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'savings_product_id' => $savingsProduct->id,
            'current_balance' => 500000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            return $widgets['savings']['total_balance'] == 500000;
        });
    }

    // =========================================================================
    // BRANCH MANAGER DASHBOARD
    // =========================================================================

    public function test_branch_manager_sees_branch_dashboard(): void
    {
        $org = Organization::factory()->create();
        $branch = Branch::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Branch Manager');
        $branch->users()->attach($user->id);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertViewHas('dashboard_type', 'branch_manager');
    }

    public function test_branch_manager_sees_only_own_branch_stats(): void
    {
        $org = Organization::factory()->create();
        $branchA = Branch::factory()->create(['organization_id' => $org->id]);
        $branchB = Branch::factory()->create(['organization_id' => $org->id]);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Branch Manager');
        $branchA->users()->attach($user->id);

        Member::factory()->count(2)->create(['branch_id' => $branchA->id, 'organization_id' => $org->id]);
        Member::factory()->count(5)->create(['branch_id' => $branchB->id, 'organization_id' => $org->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            return $widgets['members']['total'] === 2;
        });
    }

    public function test_branch_manager_cannot_see_other_branch(): void
    {
        $org = Organization::factory()->create();
        $branchA = Branch::factory()->create(['organization_id' => $org->id]);
        $branchB = Branch::factory()->create(['organization_id' => $org->id]);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Branch Manager');
        $branchA->users()->attach($user->id);

        VicobaGroup::factory()->count(3)->create(['branch_id' => $branchB->id]);
        Member::factory()->count(4)->create(['branch_id' => $branchB->id, 'organization_id' => $org->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            return $widgets['branch']['groups'] === 0 && $widgets['members']['total'] === 0;
        });
    }

    // =========================================================================
    // STAFF DASHBOARD (Permission-Based)
    // =========================================================================

    public function test_staff_sees_permission_based_dashboard(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Loan Officer');
        $org->users()->attach($user->id);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertViewHas('dashboard_type', 'staff');
    }

    public function test_staff_sees_only_permitted_widgets(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Accountant'); // Only has dashboard, accounting, reports

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            // Accountant has no member, savings_account, share_account, loan_application permissions
            return !isset($widgets['members'])
                && !isset($widgets['savings'])
                && !isset($widgets['shares'])
                && !isset($widgets['loans']);
        });
    }

    public function test_loan_officer_sees_loan_and_member_widgets(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Loan Officer');
        $org->users()->attach($user->id);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            return isset($widgets['members']) && isset($widgets['loans']);
        });
    }

    public function test_treasurer_sees_finance_widgets(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Treasurer');
        $org->users()->attach($user->id);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            return isset($widgets['savings']) && isset($widgets['shares']) && isset($widgets['welfare']);
        });
    }

    // =========================================================================
    // MEMBER DASHBOARD
    // =========================================================================

    public function test_member_sees_member_dashboard(): void
    {
        $user = User::where('email', 'test@financepro.co.tz')->first();

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertViewHas('dashboard_type', 'member');
    }

    public function test_member_without_member_record_sees_empty_dashboard(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('VICOBA Member');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertViewHas('dashboard_type', 'member');
        $response->assertViewHas('widgets', []);
    }

    public function test_member_sees_own_data_only(): void
    {
        $user = User::where('email', 'test@financepro.co.tz')->first();
        $member = $user->member;

        $org = Organization::factory()->create();
        $branch = Branch::factory()->create(['organization_id' => $org->id]);
        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $org->id]);

        // Other member's savings
        $otherMember = Member::factory()->create(['organization_id' => $org->id, 'branch_id' => $branch->id]);
        SavingsAccount::factory()->create([
            'member_id' => $otherMember->id,
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'savings_product_id' => $savingsProduct->id,
            'current_balance' => 1000000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) use ($member) {
            // Member should only see their own data, not other member's 1M balance
            return !isset($widgets['savings']) || $widgets['savings']['balance'] != 1000000;
        });
    }

    // =========================================================================
    // SIDEBAR VISIBILITY
    // =========================================================================

    public function test_sidebar_hides_organization_for_user_without_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('VICOBA Member');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertDontSee('Organizations');
        $response->assertDontSee('Branches');
        $response->assertDontSee('VICOBA Groups');
    }

    public function test_sidebar_shows_organization_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertSee('Organizations');
        $response->assertSee('Branches');
        $response->assertSee('VICOBA Groups');
    }

    public function test_sidebar_hides_finance_for_user_without_finance_permissions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Secretary'); // Has dashboard, member, group, meetings, reports only

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertDontSee('Savings Plans');
        $response->assertDontSee('Savings Accounts');
        $response->assertDontSee('Share Plans');
        $response->assertDontSee('Welfare Funds');
    }

    public function test_sidebar_hides_loans_for_user_without_loan_permissions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Treasurer'); // Has savings, shares, welfare but no loans

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertDontSee('Loan Plans');
        $response->assertDontSee('Applications');
    }

    public function test_sidebar_hides_admin_for_user_without_admin_permissions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Branch Manager'); // No user.view, role.view, permission.view

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertDontSee('Users');
        $response->assertDontSee('Roles');
        $response->assertDontSee('Permissions');
    }

    public function test_sidebar_shows_admin_for_super_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertSee('Users');
        $response->assertSee('Roles');
        $response->assertSee('Permissions');
    }

    public function test_sidebar_shows_reports_for_user_with_reports_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Auditor'); // Has dashboard, reports, audit

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertSee('Reports');
    }

    public function test_sidebar_hides_reports_for_user_without_reports_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('VICOBA Member'); // No reports.view

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertDontSee('Reports');
    }

    public function test_sidebar_hides_members_for_user_without_member_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Accountant'); // No member.view

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertDontSee('Members');
    }

    public function test_sidebar_shows_finance_for_user_with_savings_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Treasurer'); // Has savings, shares, welfare

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertSee('Savings');
        $response->assertSee('Shares');
        $response->assertSee('Welfare');
    }

    // =========================================================================
    // QUICK ACTIONS VISIBILITY
    // =========================================================================

    public function test_quick_actions_respect_permissions_for_super_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertSee('Create Organization');
        $response->assertSee('Manage Users');
        $response->assertSee('View Members');
    }

    public function test_quick_actions_hide_unauthorized_for_non_admin(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Branch Manager');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertDontSee('Create Organization');
        $response->assertDontSee('Manage Users');
    }

    public function test_quick_actions_show_member_create_for_org_admin(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Organization Administrator');
        $org->users()->attach($user->id);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertSee('Add Member');
        $response->assertSee('Add Branch');
        $response->assertSee('Add VICOBA Group');
    }

    public function test_quick_actions_show_loan_for_loan_officer(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Loan Officer');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertSee('New Loan Application');
        $response->assertSee('View Applications');
    }

    // =========================================================================
    // TENANT SCOPING
    // =========================================================================

    public function test_org_a_cannot_see_org_b_statistics(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $userA = User::factory()->create(['status' => 'active']);
        $userA->assignRole('Organization Administrator');
        $orgA->users()->attach($userA->id);

        // OrgB data
        $branchB = Branch::factory()->create(['organization_id' => $orgB->id]);
        Member::factory()->count(10)->create(['organization_id' => $orgB->id, 'branch_id' => $branchB->id]);
        VicobaGroup::factory()->count(5)->create(['branch_id' => $branchB->id]);

        $response = $this->actingAs($userA)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            return $widgets['members']['total'] === 0
                && $widgets['organization']['branches'] === 0
                && $widgets['organization']['groups'] === 0;
        });
    }

    public function test_branch_a_cannot_see_branch_b_statistics(): void
    {
        $org = Organization::factory()->create();
        $branchA = Branch::factory()->create(['organization_id' => $org->id]);
        $branchB = Branch::factory()->create(['organization_id' => $org->id]);

        $userA = User::factory()->create(['status' => 'active']);
        $userA->assignRole('Branch Manager');
        $branchA->users()->attach($userA->id);

        Member::factory()->count(10)->create(['branch_id' => $branchB->id, 'organization_id' => $org->id]);
        VicobaGroup::factory()->count(5)->create(['branch_id' => $branchB->id]);

        $response = $this->actingAs($userA)->get(route('dashboard'));
        $response->assertViewHas('widgets', function ($widgets) {
            return $widgets['members']['total'] === 0 && $widgets['branch']['groups'] === 0;
        });
    }

    public function test_two_org_admins_see_different_data(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $userA = User::factory()->create(['status' => 'active']);
        $userA->assignRole('Organization Administrator');
        $orgA->users()->attach($userA->id);

        $userB = User::factory()->create(['status' => 'active']);
        $userB->assignRole('Organization Administrator');
        $orgB->users()->attach($userB->id);

        Member::factory()->count(3)->create(['organization_id' => $orgA->id]);
        Member::factory()->count(7)->create(['organization_id' => $orgB->id]);

        $responseA = $this->actingAs($userA)->get(route('dashboard'));
        $responseA->assertViewHas('widgets', fn ($w) => $w['members']['total'] === 3);

        $responseB = $this->actingAs($userB)->get(route('dashboard'));
        $responseB->assertViewHas('widgets', fn ($w) => $w['members']['total'] === 7);
    }

    // =========================================================================
    // DASHBOARD SERVICE
    // =========================================================================

    public function test_dashboard_service_resolves_super_admin(): void
    {
        $service = new \App\Services\DashboardService($this->admin);
        $data = $service->resolve();

        $this->assertEquals('super_admin', $data['dashboard_type']);
        $this->assertArrayHasKey('organizations', $data['widgets']);
        $this->assertArrayHasKey('branches', $data['widgets']);
        $this->assertArrayHasKey('members', $data['widgets']);
    }

    public function test_dashboard_service_resolves_org_admin(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Organization Administrator');
        $org->users()->attach($user->id);

        $service = new \App\Services\DashboardService($user);
        $data = $service->resolve();

        $this->assertEquals('org_admin', $data['dashboard_type']);
        $this->assertArrayHasKey('organization', $data['widgets']);
        $this->assertArrayHasKey('members', $data['widgets']);
    }

    public function test_dashboard_service_resolves_branch_manager(): void
    {
        $org = Organization::factory()->create();
        $branch = Branch::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Branch Manager');
        $branch->users()->attach($user->id);

        $service = new \App\Services\DashboardService($user);
        $data = $service->resolve();

        $this->assertEquals('branch_manager', $data['dashboard_type']);
        $this->assertArrayHasKey('branch', $data['widgets']);
        $this->assertArrayHasKey('members', $data['widgets']);
    }

    public function test_dashboard_service_resolves_staff(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Loan Officer');

        $service = new \App\Services\DashboardService($user);
        $data = $service->resolve();

        $this->assertEquals('staff', $data['dashboard_type']);
        $this->assertArrayHasKey('widgets', $data);
    }

    public function test_dashboard_service_resolves_member(): void
    {
        $user = User::where('email', 'test@financepro.co.tz')->first();

        $service = new \App\Services\DashboardService($user);
        $data = $service->resolve();

        $this->assertEquals('member', $data['dashboard_type']);
        $this->assertArrayHasKey('widgets', $data);
    }

    // =========================================================================
    // DASHBOARD WIDGETS ARE READ-ONLY
    // =========================================================================

    public function test_dashboard_does_not_modify_financial_data(): void
    {
        $org = Organization::factory()->create();
        $branch = Branch::factory()->create(['organization_id' => $org->id]);
        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $org->id]);
        $member = Member::factory()->create(['organization_id' => $org->id, 'branch_id' => $branch->id]);
        $account = SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'savings_product_id' => $savingsProduct->id,
            'current_balance' => 100000,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)->get(route('dashboard'));

        $account->refresh();
        $this->assertEquals(100000, $account->current_balance);
    }

    // =========================================================================
    // PAGE LOADS
    // =========================================================================

    public function test_dashboard_page_has_correct_title(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertStatus(200);
    }

    public function test_dashboard_shows_welcome_message(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertSee('Welcome back');
        $response->assertSee('System Administrator');
    }

    public function test_dashboard_shows_scope_label(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertSee('Platform Administration');
    }
}
