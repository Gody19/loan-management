<?php

namespace Tests\Feature\MemberPortal;

use App\Enums\GuarantorStatus;
use App\Enums\LoanPurpose;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Enums\UserStatus;
use App\Models\LoanApplication;
use App\Models\LoanApplicationGuarantor;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\User;
use App\Services\GuarantorEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberGuarantorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $applicantUser;
    private Member $applicant;
    private User $guarantorUser;
    private Member $guarantor;
    private LoanPlan $plan;
    private LoanApplication $application;

    private function createRoles(): void
    {
        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'VICOBA Member', 'guard_name' => 'web']);
    }

    private function createOrgWithBranch(string $suffix = ''): array
    {
        $org = \App\Models\Organization::create([
            'name' => "Org {$suffix}",
            'registration_number' => "ORG-{$suffix}-001",
            'phone' => '+255700000000',
            'email' => "test@{$suffix}.com",
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'status' => 'active',
        ]);

        $branch = \App\Models\Branch::create([
            'organization_id' => $org->id,
            'name' => 'Main Branch',
            'code' => "BR-{$suffix}-001",
            'status' => 'active',
        ]);

        $group = \App\Models\VicobaGroup::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'name' => "Group {$suffix}",
            'code' => "GRP-{$suffix}-001",
            'status' => 'active',
        ]);

        return ['organization' => $org, 'branch' => $branch, 'group' => $group];
    }

    private function createMemberWithUser(array $orgData, string $name = 'Member'): array
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);

        $member = Member::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $orgData['organization']->id,
            'branch_id' => $orgData['branch']->id,
            'vicoba_group_id' => $orgData['group']->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $user->assignRole('VICOBA Member');

        return ['user' => $user, 'member' => $member];
    }

    private function makeLoanPlan(int $orgId): LoanPlan
    {
        return LoanPlan::create([
            'organization_id' => $orgId,
            'name' => 'Standard Loan',
            'code' => 'STD-' . uniqid(),
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Administrator');

        $orgData = $this->createOrgWithBranch('main');

        ['user' => $applicantUser, 'member' => $applicant] = $this->createMemberWithUser($orgData, 'Applicant');
        $this->applicantUser = $applicantUser;
        $this->applicant = $applicant;

        ['user' => $guarantorUser, 'member' => $guarantor] = $this->createMemberWithUser($orgData, 'Guarantor');
        $this->guarantorUser = $guarantorUser;
        $this->guarantor = $guarantor;
        $this->guarantor->update(['national_id' => '1234567890123456']);
        $this->guarantor->refresh();

        $this->plan = $this->makeLoanPlan($orgData['organization']->id);

        $this->application = app(\App\Services\LoanApplicationService::class)->create(
            [
                'branch_id' => $this->applicant->branch_id,
                'vicoba_group_id' => $this->applicant->vicoba_group_id,
                'requested_amount' => 500000,
                'requested_term' => 6,
                'repayment_frequency' => $this->plan->repayment_frequency,
                'loan_purpose' => LoanPurpose::Personal,
                'purpose_description' => 'Business expansion',
            ],
            $this->applicant,
            $this->plan
        );

        app(\App\Services\LoanApplicationService::class)->addGuarantor(
            $this->application,
            ['guaranteed_amount' => 500000],
            $this->guarantor
        );
    }

    private function applicationService(): \App\Services\LoanApplicationService
    {
        return app(\App\Services\LoanApplicationService::class);
    }

    // ==================== Authentication Tests ====================

    public function test_guest_cannot_access_guarantor_routes(): void
    {
        $response = $this->get(route('member.guarantor.requests'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_cannot_access_guarantor_routes(): void
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('member.guarantor.requests'));
        $response->assertStatus(403);
    }

    public function test_member_can_access_guarantor_requests(): void
    {
        $this->actingAs($this->guarantorUser);
        $response = $this->get(route('member.guarantor.requests'));
        $response->assertStatus(200);
        $response->assertSee('Guarantor Requests');
    }

    // ==================== Request List Tests ====================

    public function test_guarantor_sees_own_requests(): void
    {
        $this->actingAs($this->guarantorUser);
        $response = $this->get(route('member.guarantor.requests'));
        $response->assertStatus(200);
        $response->assertSee($this->application->application_number);
    }

    public function test_guarantor_does_not_see_other_requests(): void
    {
        $orgData = $this->createOrgWithBranch('other');
        ['user' => $otherUser, 'member' => $otherMember] = $this->createMemberWithUser($orgData, 'Other');
        $otherPlan = $this->makeLoanPlan($orgData['organization']->id);

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

        $this->actingAs($this->guarantorUser);
        $response = $this->get(route('member.guarantor.requests'));
        $response->assertStatus(200);
        $response->assertDontSee($otherApp->application_number);
    }

    // ==================== Request Detail Tests ====================

    public function test_guarantor_can_view_own_request_detail(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $response = $this->get(route('member.guarantor.request', $guarantorRequest));
        $response->assertStatus(200);
        $response->assertSee($this->application->application_number);
    }

    public function test_guarantor_cannot_view_other_member_request(): void
    {
        $orgData = $this->createOrgWithBranch('other2');
        ['user' => $otherUser, 'member' => $otherMember] = $this->createMemberWithUser($orgData, 'Other2');
        $otherPlan = $this->makeLoanPlan($orgData['organization']->id);

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

        ['user' => $otherGuarantorUser, 'member' => $otherGuarantor] = $this->createMemberWithUser($orgData, 'OtherGuarantor');
        $this->applicationService()->addGuarantor($otherApp, ['guaranteed_amount' => 500000], $otherGuarantor);

        $otherGuarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $otherGuarantor->id)->first();

        $this->actingAs($this->guarantorUser);
        $response = $this->get(route('member.guarantor.request', $otherGuarantorRequest));
        $response->assertStatus(404);
    }

    // ==================== Accept Tests (Phase 10.6: stays Pending for org review) ====================

    public function test_guarantor_can_accept_pending_request(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);

        $response = $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $guarantorRequest->refresh();
        $this->assertEquals('pending', $guarantorRequest->status->value);
        $this->assertNotNull($guarantorRequest->confirmed_at);
    }

    public function test_guarantor_cannot_accept_already_accepted_request(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);

        $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);

        $response = $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);
        $response->assertSessionHasErrors('error');
    }

    public function test_guarantor_cannot_accept_rejected_request(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);

        $this->post(route('member.guarantor.reject', $guarantorRequest), [
            'rejection_reason' => 'Cannot guarantee at this time.',
        ]);

        $response = $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);
        $response->assertSessionHasErrors('error');
    }

    public function test_member_cannot_accept_other_member_request(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->applicantUser);
        $response = $this->post(route('member.guarantor.accept', $guarantorRequest));
        $response->assertStatus(404);
    }

    // ==================== Reject Tests ====================

    public function test_guarantor_can_reject_pending_request(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);

        $response = $this->post(route('member.guarantor.reject', $guarantorRequest), [
            'rejection_reason' => 'Cannot guarantee at this time.',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $guarantorRequest->refresh();
        $this->assertEquals('rejected', $guarantorRequest->status->value);
        $this->assertNotNull($guarantorRequest->rejected_at);
        $this->assertEquals('Cannot guarantee at this time.', $guarantorRequest->rejection_reason);
    }

    public function test_rejection_requires_reason(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $response = $this->post(route('member.guarantor.reject', $guarantorRequest), []);
        $response->assertSessionHasErrors('rejection_reason');
    }

    public function test_guarantor_cannot_reject_already_rejected_request(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);

        $this->post(route('member.guarantor.reject', $guarantorRequest), [
            'rejection_reason' => 'First rejection.',
        ]);

        $response = $this->post(route('member.guarantor.reject', $guarantorRequest), [
            'rejection_reason' => 'Second rejection.',
        ]);
        $response->assertSessionHasErrors('error');
    }

    public function test_guarantor_cannot_reject_already_accepted_request(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);

        $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);

        $response = $this->post(route('member.guarantor.reject', $guarantorRequest), [
            'rejection_reason' => 'Trying to reject after accept.',
        ]);
        $response->assertSessionHasErrors('error');
    }

    public function test_member_cannot_reject_other_member_request(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->applicantUser);
        $response = $this->post(route('member.guarantor.reject', $guarantorRequest), [
            'rejection_reason' => 'Trying to reject as applicant.',
        ]);
        $response->assertStatus(404);
    }

    // ==================== Application Status Tests ====================

    public function test_guarantor_acceptance_does_not_auto_approve_application(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);

        $this->application->refresh();
        $this->assertNotEquals('approved', $this->application->status->value);
    }

    public function test_applicant_sees_guarantor_status_in_application_detail(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);

        $this->actingAs($this->applicantUser);
        $response = $this->get(route('member.loans.application', $this->application));
        $response->assertStatus(200);
        $response->assertSee('Pending');
    }

    public function test_applicant_cannot_manipulate_guarantor_status(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->applicantUser);

        $response = $this->post(route('member.guarantor.accept', $guarantorRequest));
        $response->assertStatus(404);

        $guarantorRequest->refresh();
        $this->assertEquals('pending', $guarantorRequest->status->value);
    }

    // ==================== Cross-Tenant Tests ====================

    public function test_cross_tenant_guarantor_cannot_access_request(): void
    {
        $orgData = $this->createOrgWithBranch('tenant');
        ['user' => $tenantUser, 'member' => $tenantMember] = $this->createMemberWithUser($orgData, 'Tenant');

        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($tenantUser);
        $response = $this->get(route('member.guarantor.request', $guarantorRequest));
        $response->assertStatus(404);
    }

    public function test_cross_tenant_cannot_accept_request(): void
    {
        $orgData = $this->createOrgWithBranch('tenant2');
        ['user' => $tenantUser, 'member' => $tenantMember] = $this->createMemberWithUser($orgData, 'Tenant2');

        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($tenantUser);
        $response = $this->post(route('member.guarantor.accept', $guarantorRequest));
        $response->assertStatus(404);
    }

    public function test_cross_tenant_cannot_reject_request(): void
    {
        $orgData = $this->createOrgWithBranch('tenant3');
        ['user' => $tenantUser, 'member' => $tenantMember] = $this->createMemberWithUser($orgData, 'Tenant3');

        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($tenantUser);
        $response = $this->post(route('member.guarantor.reject', $guarantorRequest), [
            'rejection_reason' => 'Cross tenant.',
        ]);
        $response->assertStatus(404);
    }

    // ==================== Security Tests ====================

    public function test_rejection_reason_is_sanitized(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $response = $this->post(route('member.guarantor.reject', $guarantorRequest), [
            'rejection_reason' => str_repeat('A', 2000),
        ]);
        $response->assertSessionHasErrors('rejection_reason');
    }

    // ==================== Regression Tests ====================

    public function test_guarantor_requests_page_shows_correct_status(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $response = $this->get(route('member.guarantor.requests'));
        $response->assertStatus(200);
        $response->assertSee('Pending');
        $response->assertSee($this->application->application_number);
    }

    public function test_no_financial_transactions_on_accept(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);

        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('loan_repayments', 0);
        $this->assertDatabaseCount('journal_entries', 0);

        $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);

        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('loan_repayments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    // ==================== Phase 10.6: Member Search Tests ====================

    public function test_member_can_search_other_members(): void
    {
        $this->actingAs($this->guarantorUser);
        $response = $this->getJson(route('member.guarantor.search', ['q' => $this->applicant->first_name]));
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['id' => $this->applicant->id]);
    }

    public function test_search_returns_eligibility_status(): void
    {
        $this->actingAs($this->guarantorUser);
        $response = $this->getJson(route('member.guarantor.search', ['q' => $this->applicant->first_name]));
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'eligible' => true,
            'active_guarantees' => 0,
        ]);
    }

    public function test_search_excludes_self(): void
    {
        $this->actingAs($this->guarantorUser);
        $response = $this->getJson(route('member.guarantor.search', ['q' => $this->guarantor->first_name]));
        $response->assertStatus(200);
        $response->assertJsonCount(0);
    }

    public function test_guest_cannot_search(): void
    {
        $response = $this->getJson(route('member.guarantor.search', ['q' => 'test']));
        $response->assertStatus(401);
    }

    // ==================== Phase 10.6: Offer as Guarantor Tests ====================

    public function test_member_can_view_offer_form(): void
    {
        $this->actingAs($this->guarantorUser);
        $response = $this->get(route('member.guarantor.offer'));
        $response->assertStatus(200);
        $response->assertSee('Offer as Guarantor');
    }

    public function test_member_can_offer_as_guarantor(): void
    {
        $this->actingAs($this->guarantorUser);
        $response = $this->post(route('member.guarantor.store-offer'), [
            'loan_application_id' => $this->application->id,
            'guaranteed_amount' => 250000,
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('loan_application_guarantors', [
            'loan_application_id' => $this->application->id,
            'guarantor_member_id' => $this->guarantor->id,
            'guaranteed_amount' => 250000,
            'status' => 'pending',
        ]);
    }

    public function test_member_cannot_guarantee_own_application(): void
    {
        $this->actingAs($this->applicantUser);
        $response = $this->post(route('member.guarantor.store-offer'), [
            'loan_application_id' => $this->application->id,
            'guaranteed_amount' => 250000,
        ]);
        $response->assertSessionHasErrors('error');
    }

    // ==================== Phase 10.6: My Guarantees Tests ====================

    public function test_member_can_view_my_guarantees(): void
    {
        $this->actingAs($this->guarantorUser);
        $response = $this->get(route('member.my-guarantees'));
        $response->assertStatus(200);
        $response->assertSee('My Guarantees');
    }

    // ==================== Phase 10.6: Eligibility Service Tests ====================

    public function test_eligibility_service_allows_new_guarantee(): void
    {
        $service = app(GuarantorEligibilityService::class);
        $result = $service->canGuarantee($this->guarantor, $this->application);

        $this->assertTrue($result['eligible']);
    }

    public function test_eligibility_service_blocks_self_guarantee(): void
    {
        $service = app(GuarantorEligibilityService::class);
        $result = $service->canGuarantee($this->applicant, $this->application);

        $this->assertFalse($result['eligible']);
    }

    public function test_eligibility_service_blocks_inactive_member(): void
    {
        $this->guarantor->update(['membership_status' => MemberStatus::Inactive]);

        $service = app(GuarantorEligibilityService::class);
        $result = $service->canGuarantee($this->guarantor, $this->application);

        $this->assertFalse($result['eligible']);
    }

    // ==================== Phase 10.6: Rejected Guarantor Replacement Tests ====================

    public function test_applicant_can_remove_rejected_guarantor(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $this->post(route('member.guarantor.reject', $guarantorRequest), [
            'rejection_reason' => 'Cannot guarantee.',
        ]);

        $this->actingAs($this->applicantUser);
        $response = $this->delete(route('member.loans.remove-guarantor', [$this->application, $guarantorRequest]));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('loan_application_guarantors', [
            'id' => $guarantorRequest->id,
        ]);
    }

    // ==================== Phase 10.6: Organization Review Flow Tests ====================

    public function test_guarantor_remains_pending_after_member_acceptance(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);

        $guarantorRequest->refresh();
        $this->assertEquals('pending', $guarantorRequest->status->value);
        $this->assertNotNull($guarantorRequest->confirmed_at);
    }

    public function test_admin_can_approve_pending_guarantor(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);

        $this->actingAs($this->admin);
        $response = $this->post(route('loan-guarantors.approve', $guarantorRequest));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $guarantorRequest->refresh();
        $this->assertEquals('accepted', $guarantorRequest->status->value);
    }

    public function test_admin_can_reject_pending_guarantor(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);

        $this->actingAs($this->admin);
        $response = $this->post(route('loan-guarantors.reject', $guarantorRequest), [
            'rejection_reason' => 'Policy violation.',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $guarantorRequest->refresh();
        $this->assertEquals('rejected', $guarantorRequest->status->value);
        $this->assertEquals('Policy violation.', $guarantorRequest->rejection_reason);
    }

    public function test_admin_cannot_approve_already_reviewed_guarantor(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);

        $this->actingAs($this->admin);
        $this->post(route('loan-guarantors.approve', $guarantorRequest));

        $response = $this->post(route('loan-guarantors.approve', $guarantorRequest));
        $response->assertSessionHas('error');
    }

    public function test_admin_review_queue_shows_pending_guarantors(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);

        $this->actingAs($this->admin);
        $response = $this->get(route('guarantor-reviews.index'));
        $response->assertStatus(200);
        $response->assertSee($this->application->application_number);
    }

    // ==================== Phase 10.6: Active Guarantee Blocking Tests ====================

    public function test_eligibility_blocks_with_active_guarantee(): void
    {
        $guarantorRequest = LoanApplicationGuarantor::where('guarantor_member_id', $this->guarantor->id)->first();
        $this->actingAs($this->guarantorUser);
        $this->post(route('member.guarantor.accept', $guarantorRequest), [
            'guaranteed_amount' => 500000,
        ]);

        $this->actingAs($this->admin);
        $this->post(route('loan-guarantors.approve', $guarantorRequest));

        $orgData = $this->createOrgWithBranch('elig');
        ['user' => $eligApplicantUser, 'member' => $eligApplicant] = $this->createMemberWithUser($orgData, 'EligApplicant');
        $eligPlan = $this->makeLoanPlan($orgData['organization']->id);
        $eligApp = $this->applicationService()->create(
            [
                'branch_id' => $eligApplicant->branch_id,
                'vicoba_group_id' => $eligApplicant->vicoba_group_id,
                'requested_amount' => 200000,
                'requested_term' => 6,
                'repayment_frequency' => $eligPlan->repayment_frequency,
                'loan_purpose' => LoanPurpose::Personal,
            ],
            $eligApplicant,
            $eligPlan
        );

        $service = app(GuarantorEligibilityService::class);
        $result = $service->canGuarantee($this->guarantor, $eligApp);

        $this->assertFalse($result['eligible']);
    }
}
