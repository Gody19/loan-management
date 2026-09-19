<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\HasAccountingSetup;

class LoanRepaymentCollectionTest extends TestCase
{
    use RefreshDatabase, HasAccountingSetup;

    protected Organization $organization;
    protected Branch $branch;
    protected VicobaGroup $group;
    protected User $admin;
    protected Member $member;
    protected Loan $loan;
    protected PaymentMethod $paymentMethod;
    protected LoanPlan $loanPlan;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'loan-repayments.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'loan-repayments.create', 'guard_name' => 'web']);

        $superAdminRole = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        Role::create(['name' => 'Organization Admin', 'guard_name' => 'web']);

        $this->organization = Organization::create([
            'name' => 'Test Org',
            'registration_number' => 'ORG-TEST01',
            'phone' => '+255123456789',
            'email' => 'org@test.com',
            'region' => 'Dar es Salaam',
            'district' => 'Dar es Salaam',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Branch',
            'code' => 'BR-TEST01',
            'status' => 'active',
        ]);

        $this->group = VicobaGroup::create([
            'branch_id' => $this->branch->id,
            'name' => 'Test Group',
            'code' => 'GRP-TEST01',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Administrator');
        $this->admin->organizations()->attach($this->organization);

        $this->setUpAccountingFor($this->admin, $this->organization);

        $this->member = Member::create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'member_number' => 'VCB-000001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'phone' => '+255712345678',
            'email' => 'john.doe@example.com',
            'joining_date' => now()->toDateString(),
            'membership_status' => MemberStatus::Active,
        ]);

        $this->loanPlan = LoanPlan::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $loanApplication = LoanApplication::create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->loanPlan->id,
            'application_number' => 'LA-TEST-000001',
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'business',
            'purpose_description' => 'Test loan',
            'application_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $this->loan = Loan::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->loanPlan->id,
            'loan_application_id' => $loanApplication->id,
            'principal_amount' => 500000,
            'outstanding_balance' => 500000,
            'amount_paid' => 0,
            'total_amount' => 560000,
        ]);

        LoanRepaymentSchedule::create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
            'installment_number' => 1,
            'due_date' => now()->addMonth(),
            'principal_amount' => 41666.67,
            'interest_amount' => 5000.00,
            'total_amount' => 46666.67,
            'amount_paid' => 0,
            'outstanding_amount' => 46666.67,
            'running_balance' => 458333.33,
            'status' => 'pending',
            'days_overdue' => 0,
            'late_fee' => 0,
        ]);

        $this->paymentMethod = PaymentMethod::create([
            'organization_id' => $this->organization->id,
            'name' => 'Cash',
            'code' => 'PMT-TEST01',
            'type' => 'cash',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_guest_cannot_access_repayment_collection(): void
    {
        $response = $this->get('/loan-repayments-collection');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_access_repayment_collection_search_page(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/loan-repayments-collection');
        $response->assertStatus(200);
    }

    public function test_search_returns_matching_members_as_json(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/loan-repayments-collection/search-members?search=John');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'members' => [
                '*' => ['id', 'member_number', 'full_name', 'phone', 'group_name', 'active_loans'],
            ],
        ]);
        $response->assertJsonCount(1, 'members');
    }

    public function test_search_requires_minimum_2_characters(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/loan-repayments-collection/search-members?search=A');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'members');
    }

    public function test_admin_can_view_member_loans(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get("/loan-repayments-collection/{$this->member->id}/loans");
        $response->assertStatus(200);
    }

    public function test_admin_can_access_repayment_create_form(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get("/loan-repayments-collection/{$this->loan->id}/repayment/create");
        $response->assertStatus(200);
    }

    public function test_admin_can_submit_repayment(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post("/loan-repayments-collection/{$this->loan->id}/repayment", [
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'payment_method_id' => $this->paymentMethod->id,
            'notes' => 'Test payment',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_repayment_updates_loan_balance(): void
    {
        $this->actingAs($this->admin);

        $this->post("/loan-repayments-collection/{$this->loan->id}/repayment", [
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $this->loan->refresh();
        $this->assertEquals('50000.00', $this->loan->amount_paid);
        $this->assertEquals('450000.00', $this->loan->outstanding_balance);
    }

    public function test_repayment_creates_repayment_record(): void
    {
        $this->actingAs($this->admin);

        $this->post("/loan-repayments-collection/{$this->loan->id}/repayment", [
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $this->assertDatabaseHas('loan_repayments', [
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
            'amount' => 50000,
            'payment_method' => 'cash',
            'status' => 'posted',
        ]);
    }

    public function test_repayment_requires_amount(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post("/loan-repayments-collection/{$this->loan->id}/repayment", [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_repayment_requires_payment_method(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post("/loan-repayments-collection/{$this->loan->id}/repayment", [
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('payment_method');
    }

    public function test_repayment_requires_payment_date(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post("/loan-repayments-collection/{$this->loan->id}/repayment", [
            'amount' => 50000,
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('payment_date');
    }

    public function test_repayment_rejects_future_date(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post("/loan-repayments-collection/{$this->loan->id}/repayment", [
            'amount' => 50000,
            'payment_date' => now()->addDays(5)->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('payment_date');
    }

    public function test_repayment_rejects_zero_amount(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post("/loan-repayments-collection/{$this->loan->id}/repayment", [
            'amount' => 0,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_org_admin_cannot_access_member_loans_from_different_org(): void
    {
        $orgAdmin = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);
        $orgAdminRole = Role::findByName('Organization Admin', 'web');
        $orgAdminRole->givePermissionTo(['loan-repayments.view', 'loan-repayments.create']);
        $orgAdmin->assignRole('Organization Admin');
        $orgAdmin->organizations()->attach($this->organization);

        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'registration_number' => 'ORG-OTHER',
            'phone' => '+255987654321',
            'email' => 'other@test.com',
            'region' => 'Arusha',
            'district' => 'Arusha',
            'status' => 'active',
        ]);

        $otherBranch = Branch::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Branch',
            'code' => 'BR-OTHER01',
            'status' => 'active',
        ]);

        $otherGroup = VicobaGroup::create([
            'branch_id' => $otherBranch->id,
            'name' => 'Other Group',
            'code' => 'GRP-OTHER01',
            'status' => 'active',
        ]);

        $otherMember = Member::create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'vicoba_group_id' => $otherGroup->id,
            'member_number' => 'VCB-000099',
            'first_name' => 'Other',
            'last_name' => 'Person',
            'gender' => 'female',
            'phone' => '+255799999999',
            'joining_date' => now()->toDateString(),
            'membership_status' => MemberStatus::Active,
        ]);

        $this->actingAs($orgAdmin);

        $response = $this->get("/loan-repayments-collection/{$otherMember->id}/loans");
        $response->assertForbidden();
    }

    public function test_org_admin_cannot_submit_repayment_for_member_from_different_org(): void
    {
        $orgAdmin = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);
        $orgAdminRole = Role::findByName('Organization Admin', 'web');
        $orgAdminRole->givePermissionTo(['loan-repayments.view', 'loan-repayments.create']);
        $orgAdmin->assignRole('Organization Admin');
        $orgAdmin->organizations()->attach($this->organization);

        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'registration_number' => 'ORG-OTHER2',
            'phone' => '+255987654322',
            'email' => 'other2@test.com',
            'region' => 'Arusha',
            'district' => 'Arusha',
            'status' => 'active',
        ]);

        $otherBranch = Branch::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Branch 2',
            'code' => 'BR-OTHER02',
            'status' => 'active',
        ]);

        $otherGroup = VicobaGroup::create([
            'branch_id' => $otherBranch->id,
            'name' => 'Other Group 2',
            'code' => 'GRP-OTHER02',
            'status' => 'active',
        ]);

        $otherLoanPlan = LoanPlan::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $otherMember = Member::create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'vicoba_group_id' => $otherGroup->id,
            'member_number' => 'VCB-000098',
            'first_name' => 'Other',
            'last_name' => 'Person',
            'gender' => 'female',
            'phone' => '+255799999999',
            'joining_date' => now()->toDateString(),
            'membership_status' => MemberStatus::Active,
        ]);

        $otherLoanApplication = LoanApplication::create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'vicoba_group_id' => $otherGroup->id,
            'member_id' => $otherMember->id,
            'loan_plan_id' => $otherLoanPlan->id,
            'application_number' => 'LA-OTHER-000001',
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'business',
            'purpose_description' => 'Test loan',
            'application_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $otherLoan = Loan::factory()->active()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'member_id' => $otherMember->id,
            'loan_plan_id' => $otherLoanPlan->id,
            'loan_application_id' => $otherLoanApplication->id,
            'principal_amount' => 500000,
            'outstanding_balance' => 500000,
            'amount_paid' => 0,
        ]);

        $this->actingAs($orgAdmin);

        $response = $this->post("/loan-repayments-collection/{$otherLoan->id}/repayment", [
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response->assertForbidden();
    }

    public function test_search_returns_empty_for_no_match(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/loan-repayments-collection/search-members?search=ZZZZZ');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'members');
    }

    public function test_member_loans_page_shows_active_loans_only(): void
    {
        $completedLoanApplication = LoanApplication::create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->loanPlan->id,
            'application_number' => 'LA-COMP-000001',
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'business',
            'purpose_description' => 'Completed loan',
            'application_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        Loan::factory()->completed()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->loanPlan->id,
            'loan_application_id' => $completedLoanApplication->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->get("/loan-repayments-collection/{$this->member->id}/loans");
        $response->assertStatus(200);
    }
}
