<?php

namespace Tests\Feature\Finance;

use App\Enums\ApprovalAction;
use App\Enums\GuarantorStatus;
use App\Enums\LoanApplicationStatus;
use App\Models\Branch;
use App\Models\LoanApplication;
use App\Models\LoanApplicationApproval;
use App\Models\LoanApprovalLevel;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $organization;
    private Branch $branch;
    private LoanPlan $plan;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->organization = Organization::factory()->create();
        $this->branch = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->plan = LoanPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'status' => 'active',
        ]);
        $this->member = Member::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'membership_status' => 'active',
        ]);
    }

    private function createUnderReviewApplication(): LoanApplication
    {
        return LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
            'status' => LoanApplicationStatus::UnderReview,
        ]);
    }

    public function test_under_review_application_can_be_approved(): void
    {
        $application = $this->createUnderReviewApplication();

        $response = $this->actingAs($this->admin)->post(route('loan-applications.approve', $application));

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_applications', [
            'id' => $application->id,
            'status' => LoanApplicationStatus::Approved->value,
        ]);
    }

    public function test_under_review_application_can_be_rejected(): void
    {
        $application = $this->createUnderReviewApplication();

        $response = $this->actingAs($this->admin)->post(route('loan-applications.reject', $application), [
            'rejection_reason' => 'Does not meet criteria',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_applications', [
            'id' => $application->id,
            'status' => LoanApplicationStatus::Rejected->value,
            'rejection_reason' => 'Does not meet criteria',
        ]);
    }

    public function test_rejection_requires_reason(): void
    {
        $application = $this->createUnderReviewApplication();

        $response = $this->actingAs($this->admin)->post(route('loan-applications.reject', $application), [
            'rejection_reason' => '',
        ]);

        $response->assertSessionHasErrors('rejection_reason');
    }

    public function test_draft_application_cannot_be_approved(): void
    {
        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.approve', $application));

        // Service throws InvalidArgumentException, controller redirects back with error
        $response->assertRedirect();
        $this->assertDatabaseHas('loan_applications', [
            'id' => $application->id,
            'status' => LoanApplicationStatus::Draft->value,
        ]);
    }

    public function test_approved_application_has_approval_record(): void
    {
        $application = $this->createUnderReviewApplication();

        $this->actingAs($this->admin)->post(route('loan-applications.approve', $application));

        $approval = LoanApplicationApproval::where('loan_application_id', $application->id)->first();
        $this->assertNotNull($approval);
        $this->assertEquals(ApprovalAction::Approved->value, $approval->action->value);
        $this->assertEquals($this->admin->id, $approval->acted_by);
    }

    public function test_rejected_application_has_rejection_record(): void
    {
        $application = $this->createUnderReviewApplication();

        $this->actingAs($this->admin)->post(route('loan-applications.reject', $application), [
            'rejection_reason' => 'Insufficient collateral',
        ]);

        $approval = LoanApplicationApproval::where('loan_application_id', $application->id)->first();
        $this->assertNotNull($approval);
        $this->assertEquals(ApprovalAction::Rejected->value, $approval->action->value);
    }

    public function test_approval_level_is_resolved_for_application_amount(): void
    {
        $level = LoanApprovalLevel::factory()->create([
            'organization_id' => $this->organization->id,
            'minimum_amount' => 0,
            'maximum_amount' => 1000000,
            'level' => 1,
            'is_active' => true,
        ]);

        $application = $this->createUnderReviewApplication();
        $application->update(['requested_amount' => 500000]);

        $this->actingAs($this->admin)->post(route('loan-applications.approve', $application));

        $approval = LoanApplicationApproval::where('loan_application_id', $application->id)->first();
        $this->assertEquals($level->id, $approval->loan_approval_level_id);
        $this->assertEquals(1, $approval->approval_level);
    }

    public function test_approval_level_not_found_when_no_matching_level(): void
    {
        $application = $this->createUnderReviewApplication();
        $application->update(['requested_amount' => 500000]);

        $this->actingAs($this->admin)->post(route('loan-applications.approve', $application));

        $approval = LoanApplicationApproval::where('loan_application_id', $application->id)->first();
        $this->assertNotNull($approval);
        $this->assertNull($approval->loan_approval_level_id);
        $this->assertEquals(1, $approval->approval_level);
    }

    public function test_status_transition_draft_to_submitted(): void
    {
        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->assertTrue($application->status->canTransitionTo(LoanApplicationStatus::Submitted));
    }

    public function test_status_transition_submitted_to_under_review(): void
    {
        $status = LoanApplicationStatus::Submitted;
        $this->assertTrue($status->canTransitionTo(LoanApplicationStatus::UnderReview));
    }

    public function test_status_transition_under_review_to_approved(): void
    {
        $status = LoanApplicationStatus::UnderReview;
        $this->assertTrue($status->canTransitionTo(LoanApplicationStatus::Approved));
    }

    public function test_status_transition_under_review_to_rejected(): void
    {
        $status = LoanApplicationStatus::UnderReview;
        $this->assertTrue($status->canTransitionTo(LoanApplicationStatus::Rejected));
    }

    public function test_status_transition_draft_to_cancelled(): void
    {
        $status = LoanApplicationStatus::Draft;
        $this->assertTrue($status->canTransitionTo(LoanApplicationStatus::Cancelled));
    }

    public function test_status_transition_approved_to_draft_is_invalid(): void
    {
        $status = LoanApplicationStatus::Approved;
        $this->assertFalse($status->canTransitionTo(LoanApplicationStatus::Draft));
    }

    public function test_status_transition_rejected_to_approved_is_invalid(): void
    {
        $status = LoanApplicationStatus::Rejected;
        $this->assertFalse($status->canTransitionTo(LoanApplicationStatus::Approved));
    }

    public function test_status_transition_cancelled_to_submitted_is_invalid(): void
    {
        $status = LoanApplicationStatus::Cancelled;
        $this->assertFalse($status->canTransitionTo(LoanApplicationStatus::Submitted));
    }

    public function test_guarantor_can_accept(): void
    {
        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $guarantorMember = Member::factory()->create([
            'organization_id' => $this->organization->id,
            'membership_status' => 'active',
            'national_id' => '6677889900112233',
        ]);

        // Create a user linked to the guarantor member
        $guarantorUser = User::factory()->create();
        $guarantorUser->assignRole('VICOBA Member');

        $this->actingAs($this->admin)->post(route('loan-applications.guarantors.store', $application), [
            'guarantor_member_id' => $guarantorMember->id,
            'guaranteed_amount' => 250000,
        ]);

        $guarantor = \App\Models\LoanApplicationGuarantor::where('loan_application_id', $application->id)->first();

        // Link the user to the guarantor member so the policy passes
        $guarantorMember->update(['user_id' => $guarantorUser->id]);

        $response = $this->actingAs($guarantorUser)->post(route('loan-guarantors.respond', $guarantor), [
            'accept' => true,
        ]);

        $response->assertRedirect();
        $guarantor->refresh();
        $this->assertEquals(GuarantorStatus::Accepted->value, $guarantor->status->value);
    }

    public function test_guarantor_can_reject_with_reason(): void
    {
        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $guarantorMember = Member::factory()->create([
            'organization_id' => $this->organization->id,
            'membership_status' => 'active',
            'national_id' => '6677889900112233',
        ]);

        $guarantorUser = User::factory()->create();
        $guarantorUser->assignRole('VICOBA Member');

        $this->actingAs($this->admin)->post(route('loan-applications.guarantors.store', $application), [
            'guarantor_member_id' => $guarantorMember->id,
            'guaranteed_amount' => 250000,
        ]);

        $guarantor = \App\Models\LoanApplicationGuarantor::where('loan_application_id', $application->id)->first();

        $guarantorMember->update(['user_id' => $guarantorUser->id]);

        $response = $this->actingAs($guarantorUser)->post(route('loan-guarantors.respond', $guarantor), [
            'accept' => false,
            'reason' => 'Cannot afford',
        ]);

        $response->assertRedirect();
        $guarantor->refresh();
        $this->assertEquals(GuarantorStatus::Rejected->value, $guarantor->status->value);
        $this->assertEquals('Cannot afford', $guarantor->rejection_reason);
    }

    public function test_approval_level_index_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get(route('loan-approval-levels.index'));
        $response->assertStatus(200);
        $response->assertViewIs('loan-approval-levels.index');
    }

    public function test_approval_level_can_be_created(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-approval-levels.store'), [
            'organization_id' => $this->organization->id,
            'name' => 'Level 1',
            'level' => 1,
            'minimum_amount' => 0,
            'maximum_amount' => 1000000,
            'required_permission' => 'loan_application.approve',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_approval_levels', [
            'organization_id' => $this->organization->id,
            'name' => 'Level 1',
            'level' => 1,
        ]);
    }

    public function test_approval_level_can_be_updated(): void
    {
        $level = LoanApprovalLevel::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('loan-approval-levels.update', $level), [
            'organization_id' => $this->organization->id,
            'name' => 'Updated Level',
            'level' => 1,
            'minimum_amount' => 0,
            'maximum_amount' => 2000000,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_approval_levels', [
            'id' => $level->id,
            'name' => 'Updated Level',
        ]);
    }

    public function test_approval_level_can_be_deleted(): void
    {
        $level = LoanApprovalLevel::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('loan-approval-levels.destroy', $level));

        $response->assertRedirect();
        // Model uses soft deletes, so record still exists in DB
        $this->assertSoftDeleted('loan_approval_levels', ['id' => $level->id]);
    }

    public function test_approval_level_requires_valid_amounts(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-approval-levels.store'), [
            'organization_id' => $this->organization->id,
            'name' => 'Bad Level',
            'level' => 1,
            'minimum_amount' => 1000000,
            'maximum_amount' => 500000,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('maximum_amount');
    }
}
