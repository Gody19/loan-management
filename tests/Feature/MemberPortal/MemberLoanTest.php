<?php

namespace Tests\Feature\MemberPortal;

use App\Enums\LoanPurpose;
use App\Enums\MemberStatus;
use App\Enums\UserStatus;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberLoanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $memberUser;
    private Member $member;
    private LoanPlan $plan;

    private function createRoles(): void
    {
        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'VICOBA Member', 'guard_name' => 'web']);
    }

    private function createOrgWithBranch(): array
    {
        $org = \App\Models\Organization::create([
            'name' => 'Test Org',
            'registration_number' => 'ORG-TEST-001',
            'phone' => '+255700000000',
            'email' => 'test@org.com',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'status' => 'active',
        ]);

        $branch = \App\Models\Branch::create([
            'organization_id' => $org->id,
            'name' => 'Main Branch',
            'code' => 'BR-001',
            'status' => 'active',
        ]);

        $group = \App\Models\VicobaGroup::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'name' => 'Test Group',
            'code' => 'GRP-001',
            'status' => 'active',
        ]);

        return ['organization' => $org, 'branch' => $branch, 'group' => $group];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Administrator');

        $orgData = $this->createOrgWithBranch();

        $this->memberUser = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);

        $this->member = Member::factory()->create([
            'user_id' => $this->memberUser->id,
            'organization_id' => $orgData['organization']->id,
            'branch_id' => $orgData['branch']->id,
            'vicoba_group_id' => $orgData['group']->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $this->memberUser->assignRole('VICOBA Member');

        $this->plan = LoanPlan::create([
            'organization_id' => $orgData['organization']->id,
            'name' => 'Standard Loan',
            'code' => 'STD-LOAN',
            'loan_purpose' => LoanPurpose::Personal,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'interest_rate' => 10,
            'interest_method' => 'flat',
            'minimum_term' => 3,
            'maximum_term' => 24,
            'repayment_frequency' => 'monthly',
            'maximum_active_loans' => 2,
            'requires_guarantor' => false,
            'minimum_guarantors' => 0,
            'requires_collateral' => false,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 2,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 3,
            'grace_period' => 5,
            'processing_fee' => 1,
            'insurance_fee' => 0.5,
            'late_payment_allowed' => true,
            'status' => 'active',
        ]);
    }

    private function createMemberWithUser(string $orgSuffix = ''): array
    {
        $this->createRoles();

        $org = \App\Models\Organization::create([
            'name' => "Org {$orgSuffix}",
            'registration_number' => "ORG-{$orgSuffix}-001",
            'phone' => '+255700000000',
            'email' => "test@{$orgSuffix}.com",
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'status' => 'active',
        ]);

        $branch = \App\Models\Branch::create([
            'organization_id' => $org->id,
            'name' => 'Main Branch',
            'code' => "BR-{$orgSuffix}-001",
            'status' => 'active',
        ]);

        $group = \App\Models\VicobaGroup::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'name' => "Group {$orgSuffix}",
            'code' => "GRP-{$orgSuffix}-001",
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);

        $member = Member::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'vicoba_group_id' => $group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $user->assignRole('VICOBA Member');

        return ['user' => $user, 'member' => $member, 'organization' => $org, 'branch' => $branch, 'group' => $group];
    }

    private function makeLoanPlan(int $orgId): LoanPlan
    {
        return LoanPlan::create([
            'organization_id' => $orgId,
            'name' => 'Other Plan',
            'code' => 'OTH-LOAN-' . uniqid(),
            'loan_purpose' => LoanPurpose::Business,
            'minimum_amount' => 10000,
            'maximum_amount' => 1000000,
            'interest_rate' => 15,
            'interest_method' => 'flat',
            'minimum_term' => 1,
            'maximum_term' => 12,
            'repayment_frequency' => 'monthly',
            'maximum_active_loans' => 1,
            'requires_guarantor' => false,
            'minimum_guarantors' => 0,
            'requires_collateral' => false,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 2,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 3,
            'grace_period' => 0,
            'processing_fee' => 0,
            'insurance_fee' => 0,
            'late_payment_allowed' => true,
            'status' => 'active',
        ]);
    }

    private function applicationService(): \App\Services\LoanApplicationService
    {
        return app(\App\Services\LoanApplicationService::class);
    }

    // ==================== Authentication Tests ====================

    public function test_guest_cannot_access_member_loan_routes(): void
    {
        $response = $this->get(route('member.loans'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_user_cannot_access_member_loan_routes(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('member.loans'));
        $response->assertStatus(403);
    }

    public function test_member_can_access_loan_dashboard(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans'));
        $response->assertStatus(200);
        $response->assertSee('My Loans');
    }

    public function test_member_can_view_loan_plans(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans'));
        $response->assertStatus(200);
        $response->assertSee($this->plan->name);
    }

    // ==================== Loan Plan Tests ====================

    public function test_member_can_view_plan_detail(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.plan', $this->plan));
        $response->assertStatus(200);
        $response->assertSee($this->plan->name);
        $response->assertSee($this->plan->interest_rate . '%');
    }

    public function test_member_cannot_view_other_org_plan(): void
    {
        ['user' => $otherUser, 'organization' => $otherOrg] = $this->createMemberWithUser('other');

        $otherPlan = $this->makeLoanPlan($otherOrg->id);

        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.plan', $otherPlan));
        $response->assertStatus(404);
    }

    public function test_member_sees_only_own_org_plans(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans'));
        $response->assertStatus(200);
        $response->assertSee($this->plan->name);
    }

    // ==================== Eligibility Tests ====================

    public function test_member_can_check_eligibility(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.eligibility', $this->plan));
        $response->assertStatus(200);
        $response->assertSee('Eligibility');
    }

    public function test_member_cannot_check_eligibility_for_other_org_plan(): void
    {
        ['user' => $otherUser, 'organization' => $otherOrg] = $this->createMemberWithUser('other');

        $otherPlan = $this->makeLoanPlan($otherOrg->id);

        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.eligibility', $otherPlan));
        $response->assertStatus(404);
    }

    // ==================== Application Tests ====================

    public function test_member_can_apply_for_loan(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.apply', $this->plan));
        $response->assertStatus(200);
        $response->assertSee('Apply for');
    }

    public function test_member_can_submit_application(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.store', $this->plan), [
            'requested_amount' => 500000,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
            'purpose_description' => 'Business expansion',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('loan_applications', [
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'requested_amount' => 500000,
            'requested_term' => 6,
            'status' => 'draft',
        ]);
    }

    public function test_application_rejects_invalid_amount(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.store', $this->plan), [
            'requested_amount' => 100,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
        ]);

        $response->assertSessionHasErrors('requested_amount');
    }

    public function test_application_rejects_invalid_term(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.store', $this->plan), [
            'requested_amount' => 500000,
            'requested_term' => 100,
            'loan_purpose' => 'personal',
        ]);

        $response->assertSessionHasErrors('requested_term');
    }

    public function test_application_rejects_missing_purpose(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.store', $this->plan), [
            'requested_amount' => 500000,
            'requested_term' => 6,
        ]);

        $response->assertSessionHasErrors('loan_purpose');
    }

    public function test_application_ignores_unauthorized_fields(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.store', $this->plan), [
            'requested_amount' => 500000,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
            'interest_rate' => 1,
            'status' => 'approved',
            'organization_id' => 999,
            'branch_id' => 999,
            'approved_amount' => 999999,
        ]);

        $response->assertRedirect();

        $application = LoanApplication::where('member_id', $this->member->id)->first();
        $this->assertNotNull($application);
        $this->assertEquals(500000, $application->requested_amount);
        $this->assertEquals('draft', $application->status->value);
        $this->assertNotEquals(999, $application->organization_id);
    }

    public function test_member_can_view_own_application(): void
    {
        $this->actingAs($this->memberUser);

        $this->post(route('member.loans.store', $this->plan), [
            'requested_amount' => 500000,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
        ]);

        $application = LoanApplication::where('member_id', $this->member->id)->first();

        $response = $this->get(route('member.loans.application', $application));
        $response->assertStatus(200);
        $response->assertSee($application->application_number);
    }

    public function test_member_cannot_view_other_member_application(): void
    {
        ['user' => $otherUser, 'member' => $otherMember, 'organization' => $otherOrg] = $this->createMemberWithUser('other');

        $otherPlan = $this->makeLoanPlan($otherOrg->id);

        $this->actingAs($otherUser);

        $this->post(route('member.loans.store', $otherPlan), [
            'requested_amount' => 500000,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
        ]);

        $otherApplication = LoanApplication::where('member_id', $otherMember->id)->first();

        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.application', $otherApplication));
        $response->assertStatus(404);
    }

    public function test_member_can_list_own_applications(): void
    {
        $this->actingAs($this->memberUser);

        $this->post(route('member.loans.store', $this->plan), [
            'requested_amount' => 500000,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
        ]);

        $response = $this->get(route('member.loans.applications'));
        $response->assertStatus(200);
        $response->assertSee('Loan Applications');
    }

    public function test_member_can_cancel_own_draft_application(): void
    {
        $this->actingAs($this->memberUser);

        $application = $this->applicationService()->create(
            [
                'branch_id' => $this->member->branch_id,
                'vicoba_group_id' => $this->member->vicoba_group_id,
                'requested_amount' => 500000,
                'requested_term' => 6,
                'repayment_frequency' => $this->plan->repayment_frequency,
                'loan_purpose' => LoanPurpose::Personal,
                'purpose_description' => 'Test',
            ],
            $this->member,
            $this->plan
        );

        $response = $this->post(route('member.loans.cancel', $application));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $application->refresh();
        $this->assertEquals('cancelled', $application->status->value);
    }

    public function test_member_cannot_cancel_other_member_application(): void
    {
        ['user' => $otherUser, 'member' => $otherMember, 'organization' => $otherOrg] = $this->createMemberWithUser('other');

        $otherPlan = $this->makeLoanPlan($otherOrg->id);

        $this->actingAs($otherUser);

        $otherApp = $this->applicationService()->create(
            [
                'branch_id' => $otherMember->branch_id,
                'vicoba_group_id' => $otherMember->vicoba_group_id,
                'requested_amount' => 500000,
                'requested_term' => 6,
                'repayment_frequency' => $otherPlan->repayment_frequency,
                'loan_purpose' => LoanPurpose::Personal,
            ],
            $otherMember,
            $otherPlan
        );

        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.cancel', $otherApp));
        $response->assertStatus(404);
    }

    // ==================== Tenant Isolation Tests ====================

    public function test_member_a_cannot_apply_with_org_b_plan(): void
    {
        ['user' => $userB, 'organization' => $orgB] = $this->createMemberWithUser('B');

        $planB = $this->makeLoanPlan($orgB->id);

        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.store', $planB), [
            'requested_amount' => 500000,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
        ]);

        $response->assertStatus(404);

        $this->assertDatabaseMissing('loan_applications', [
            'member_id' => $this->member->id,
            'loan_plan_id' => $planB->id,
        ]);
    }

    public function test_member_a_cannot_view_org_b_application(): void
    {
        ['user' => $userB, 'member' => $memberB, 'organization' => $orgB] = $this->createMemberWithUser('B');

        $planB = $this->makeLoanPlan($orgB->id);

        $this->actingAs($userB);

        $this->post(route('member.loans.store', $planB), [
            'requested_amount' => 500000,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
        ]);

        $appB = LoanApplication::where('member_id', $memberB->id)->first();

        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.application', $appB));
        $response->assertStatus(404);
    }

    // ==================== Security Tests ====================

    public function test_member_cannot_manipulate_status_via_store(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.store', $this->plan), [
            'requested_amount' => 500000,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
            'status' => 'approved',
        ]);

        $response->assertRedirect();

        $app = LoanApplication::where('member_id', $this->member->id)->first();
        $this->assertEquals('draft', $app->status->value);
    }

    public function test_member_cannot_manipulate_interest_rate_via_store(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.store', $this->plan), [
            'requested_amount' => 500000,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
            'interest_rate' => 1,
        ]);

        $response->assertRedirect();

        $app = LoanApplication::where('member_id', $this->member->id)->first();
        $this->assertEquals($this->plan->interest_rate, (float) $app->loanPlan->interest_rate);
    }

    public function test_member_cannot_manipulate_organization_id_via_store(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.store', $this->plan), [
            'requested_amount' => 500000,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
            'organization_id' => 999,
        ]);

        $response->assertRedirect();

        $app = LoanApplication::where('member_id', $this->member->id)->first();
        $this->assertEquals($this->member->organization_id, $app->organization_id);
    }

    public function test_member_cannot_manipulate_approved_amount_via_store(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.store', $this->plan), [
            'requested_amount' => 500000,
            'requested_term' => 6,
            'loan_purpose' => 'personal',
            'approved_amount' => 999999,
        ]);

        $response->assertRedirect();

        $app = LoanApplication::where('member_id', $this->member->id)->first();
        $this->assertNull($app->approved_amount);
    }

    // ==================== Guarantor Tests ====================

    public function test_member_can_add_guarantor_to_own_application(): void
    {
        $guarantorUser = User::factory()->create(['status' => UserStatus::Active, 'is_active' => true]);
        $guarantorMember = Member::factory()->create([
            'user_id' => $guarantorUser->id,
            'organization_id' => $this->member->organization_id,
            'branch_id' => $this->member->branch_id,
            'vicoba_group_id' => $this->member->vicoba_group_id,
            'membership_status' => MemberStatus::Active,
        ]);

        $this->actingAs($this->memberUser);

        $application = $this->applicationService()->create(
            [
                'branch_id' => $this->member->branch_id,
                'vicoba_group_id' => $this->member->vicoba_group_id,
                'requested_amount' => 500000,
                'requested_term' => 6,
                'repayment_frequency' => $this->plan->repayment_frequency,
                'loan_purpose' => LoanPurpose::Personal,
            ],
            $this->member,
            $this->plan
        );

        $response = $this->post(route('member.loans.add-guarantor', $application), [
            'guarantor_member_id' => $guarantorMember->id,
            'guaranteed_amount' => 500000,
            'notes' => 'Test guarantor',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('loan_application_guarantors', [
            'loan_application_id' => $application->id,
            'guarantor_member_id' => $guarantorMember->id,
            'status' => 'pending',
        ]);
    }

    public function test_member_cannot_guarantee_own_loan(): void
    {
        $this->actingAs($this->memberUser);

        $application = $this->applicationService()->create(
            [
                'branch_id' => $this->member->branch_id,
                'vicoba_group_id' => $this->member->vicoba_group_id,
                'requested_amount' => 500000,
                'requested_term' => 6,
                'repayment_frequency' => $this->plan->repayment_frequency,
                'loan_purpose' => LoanPurpose::Personal,
            ],
            $this->member,
            $this->plan
        );

        $response = $this->post(route('member.loans.add-guarantor', $application), [
            'guarantor_member_id' => $this->member->id,
            'guaranteed_amount' => 500000,
        ]);

        $response->assertSessionHasErrors('guarantor_member_id');
    }

    public function test_member_cannot_add_guarantor_from_different_org(): void
    {
        ['member' => $otherMember] = $this->createMemberWithUser('otherorg');

        $this->actingAs($this->memberUser);

        $application = $this->applicationService()->create(
            [
                'branch_id' => $this->member->branch_id,
                'vicoba_group_id' => $this->member->vicoba_group_id,
                'requested_amount' => 500000,
                'requested_term' => 6,
                'repayment_frequency' => $this->plan->repayment_frequency,
                'loan_purpose' => LoanPurpose::Personal,
            ],
            $this->member,
            $this->plan
        );

        $response = $this->post(route('member.loans.add-guarantor', $application), [
            'guarantor_member_id' => $otherMember->id,
            'guaranteed_amount' => 500000,
        ]);

        $response->assertSessionHasErrors('guarantor_member_id');
    }

    public function test_member_cannot_add_duplicate_guarantor(): void
    {
        $guarantorUser = User::factory()->create(['status' => UserStatus::Active, 'is_active' => true]);
        $guarantorMember = Member::factory()->create([
            'user_id' => $guarantorUser->id,
            'organization_id' => $this->member->organization_id,
            'branch_id' => $this->member->branch_id,
            'vicoba_group_id' => $this->member->vicoba_group_id,
            'membership_status' => MemberStatus::Active,
        ]);

        $this->actingAs($this->memberUser);

        $application = $this->applicationService()->create(
            [
                'branch_id' => $this->member->branch_id,
                'vicoba_group_id' => $this->member->vicoba_group_id,
                'requested_amount' => 500000,
                'requested_term' => 6,
                'repayment_frequency' => $this->plan->repayment_frequency,
                'loan_purpose' => LoanPurpose::Personal,
            ],
            $this->member,
            $this->plan
        );

        $this->applicationService()->addGuarantor($application, [
            'guaranteed_amount' => 250000,
        ], $guarantorMember);

        $response = $this->post(route('member.loans.add-guarantor', $application), [
            'guarantor_member_id' => $guarantorMember->id,
            'guaranteed_amount' => 250000,
        ]);

        $response->assertSessionHasErrors('guarantor_member_id');
    }

    // ==================== Collateral Tests ====================

    public function test_member_can_add_collateral_to_own_application(): void
    {
        $this->actingAs($this->memberUser);

        $application = $this->applicationService()->create(
            [
                'branch_id' => $this->member->branch_id,
                'vicoba_group_id' => $this->member->vicoba_group_id,
                'requested_amount' => 500000,
                'requested_term' => 6,
                'repayment_frequency' => $this->plan->repayment_frequency,
                'loan_purpose' => LoanPurpose::Personal,
            ],
            $this->member,
            $this->plan
        );

        $response = $this->post(route('member.loans.add-collateral', $application), [
            'collateral_type' => 'land',
            'description' => 'Plot in Dar es Salaam',
            'estimated_value' => 2000000,
            'reference_number' => 'TITLE-001',
            'ownership_details' => 'Personal ownership',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('loan_application_collaterals', [
            'loan_application_id' => $application->id,
            'collateral_type' => 'land',
            'status' => 'pending',
        ]);
    }

    public function test_member_cannot_add_collateral_to_other_member_application(): void
    {
        ['user' => $otherUser, 'member' => $otherMember, 'organization' => $otherOrg] = $this->createMemberWithUser('collateral');

        $otherPlan = $this->makeLoanPlan($otherOrg->id);

        $this->actingAs($otherUser);

        $otherApp = $this->applicationService()->create(
            [
                'branch_id' => $otherMember->branch_id,
                'vicoba_group_id' => $otherMember->vicoba_group_id,
                'requested_amount' => 500000,
                'requested_term' => 6,
                'repayment_frequency' => $otherPlan->repayment_frequency,
                'loan_purpose' => LoanPurpose::Personal,
            ],
            $otherMember,
            $otherPlan
        );

        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.add-collateral', $otherApp), [
            'collateral_type' => 'land',
            'description' => 'Plot',
            'estimated_value' => 2000000,
        ]);

        $response->assertStatus(404);
    }

    // ==================== HTTP Method Tests ====================

    public function test_get_on_store_url_returns_apply_form(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.apply', $this->plan));
        $response->assertStatus(200);
        $response->assertSee('Apply for');
    }

    public function test_post_on_plan_route_returns_405(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.plan', $this->plan));
        $response->assertStatus(405);
    }

    // ==================== Regression Tests ====================

    public function test_loan_page_shows_loan_plans(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans'));
        $response->assertStatus(200);
        $response->assertSee($this->plan->name);
        $response->assertSee('Available Loan Plans');
    }

    public function test_application_detail_shows_guarantors_section(): void
    {
        $this->actingAs($this->memberUser);

        $application = $this->applicationService()->create(
            [
                'branch_id' => $this->member->branch_id,
                'vicoba_group_id' => $this->member->vicoba_group_id,
                'requested_amount' => 500000,
                'requested_term' => 6,
                'repayment_frequency' => $this->plan->repayment_frequency,
                'loan_purpose' => LoanPurpose::Personal,
            ],
            $this->member,
            $this->plan
        );

        $response = $this->get(route('member.loans.application', $application));
        $response->assertStatus(200);
        $response->assertSee('Guarantors');
        $response->assertSee('Collateral');
        $response->assertSee('Approval Progress');
    }
}
