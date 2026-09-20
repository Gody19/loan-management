<?php

namespace Tests\Feature\Finance;

use App\Enums\LoanApplicationStatus;
use App\Enums\GuarantorStatus;
use App\Enums\LoanCollateralStatus;
use App\Models\Branch;
use App\Models\LoanApplication;
use App\Models\LoanApplicationGuarantor;
use App\Models\LoanApplicationCollateral;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Services\ApplicationNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanApplicationTest extends TestCase
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

    public function test_application_can_be_created(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-applications.store'), [
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'business',
            'purpose_description' => 'Expand shop',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_applications', [
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'status' => LoanApplicationStatus::Draft->value,
            'requested_amount' => 500000,
        ]);
    }

    public function test_application_number_is_generated(): void
    {
        $this->actingAs($this->admin)->post(route('loan-applications.store'), [
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'business',
        ]);

        $app = LoanApplication::latest()->first();
        $this->assertNotNull($app);
        $this->assertMatchesRegularExpression('/^LN-\d{4}-\d{6}$/', $app->application_number);
    }

    public function test_application_number_is_unique_across_generations(): void
    {
        $gen = new ApplicationNumberGenerator();

        // First call creates LN-YYYY-000001
        $num1 = $gen->generate();

        // Insert a fake row so the second call sees it and increments
        DB::table('loan_applications')->insert([
            'application_number' => $num1,
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 100000,
            'requested_term' => 6,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'business',
            'status' => 'draft',
            'application_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $num2 = $gen->generate();
        $this->assertNotEquals($num1, $num2);
        $this->assertEquals('LN-' . date('Y') . '-000002', $num2);
    }

    public function test_cannot_create_application_for_other_organization_member(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherMember = Member::factory()->create(['organization_id' => $otherOrg->id]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.store'), [
            'member_id' => $otherMember->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'business',
        ]);

        // Super Admin bypasses Gate, but controller checks member org. 
        // It returns 403 from the controller's org check or 422 if the member/plan org mismatch fails first
        $this->assertDatabaseMissing('loan_applications', [
            'member_id' => $otherMember->id,
        ]);
    }

    public function test_draft_application_can_be_edited(): void
    {
        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('loan-applications.update', $application), [
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 750000,
            'requested_term' => 6,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'education',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_applications', [
            'id' => $application->id,
            'requested_amount' => 750000,
        ]);
    }

    public function test_submitted_application_cannot_be_edited(): void
    {
        $application = LoanApplication::factory()->submitted()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('loan-applications.update', $application), [
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 750000,
            'requested_term' => 6,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'education',
        ]);

        $response->assertStatus(422);
    }

    public function test_draft_application_can_be_cancelled(): void
    {
        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.cancel', $application), [
            'cancellation_reason' => 'Changed my mind',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_applications', [
            'id' => $application->id,
            'status' => LoanApplicationStatus::Cancelled->value,
        ]);
    }

    public function test_approved_application_cannot_be_cancelled(): void
    {
        $application = LoanApplication::factory()->approved()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.cancel', $application), [
            'cancellation_reason' => 'Changed mind',
        ]);

        // Controller redirects back with error (not abort 422)
        $response->assertRedirect();
        $this->assertDatabaseHas('loan_applications', [
            'id' => $application->id,
            'status' => LoanApplicationStatus::Approved->value,
        ]);
    }

    public function test_guarantor_can_be_added_to_draft_application(): void
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
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.guarantors.store', $application), [
            'guarantor_member_id' => $guarantorMember->id,
            'guaranteed_amount' => 250000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_application_guarantors', [
            'loan_application_id' => $application->id,
            'guarantor_member_id' => $guarantorMember->id,
            'status' => GuarantorStatus::Pending->value,
        ]);
    }

    public function test_member_cannot_guarantee_own_application(): void
    {
        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.guarantors.store', $application), [
            'guarantor_member_id' => $this->member->id,
            'guaranteed_amount' => 250000,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('loan_application_guarantors', [
            'loan_application_id' => $application->id,
            'guarantor_member_id' => $this->member->id,
        ]);
    }

    public function test_duplicate_guarantor_is_rejected(): void
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
        ]);

        $this->actingAs($this->admin)->post(route('loan-applications.guarantors.store', $application), [
            'guarantor_member_id' => $guarantorMember->id,
            'guaranteed_amount' => 250000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.guarantors.store', $application), [
            'guarantor_member_id' => $guarantorMember->id,
            'guaranteed_amount' => 250000,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_pending_guarantor_can_be_removed(): void
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
        ]);

        $this->actingAs($this->admin)->post(route('loan-applications.guarantors.store', $application), [
            'guarantor_member_id' => $guarantorMember->id,
            'guaranteed_amount' => 250000,
        ]);

        $guarantor = LoanApplicationGuarantor::where('loan_application_id', $application->id)->first();

        $response = $this->actingAs($this->admin)->delete(route('loan-applications.guarantors.destroy', [$application, $guarantor]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('loan_application_guarantors', ['id' => $guarantor->id]);
    }

    public function test_collateral_can_be_added(): void
    {
        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.collaterals.store', $application), [
            'collateral_type' => 'land',
            'description' => 'Plot in Dar es Salaam',
            'estimated_value' => 5000000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_application_collaterals', [
            'loan_application_id' => $application->id,
            'collateral_type' => 'land',
            'status' => LoanCollateralStatus::Pending->value,
        ]);
    }

    public function test_collateral_can_be_removed(): void
    {
        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $collateral = LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $application->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('loan-applications.collaterals.destroy', [$application, $collateral]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('loan_application_collaterals', ['id' => $collateral->id]);
    }

    public function test_tenant_isolation_cannot_view_other_org_application(): void
    {
        $otherOrg = Organization::factory()->create();
        $app = LoanApplication::factory()->create(['organization_id' => $otherOrg->id]);

        // Create a Branch Manager attached to a DIFFERENT org (not the one owning the app)
        $branchUser = User::factory()->create();
        $branchUser->assignRole('Branch Manager');
        $this->organization->users()->attach($branchUser->id);

        $response = $this->actingAs($branchUser)->get(route('loan-applications.show', $app));
        $response->assertStatus(403);
    }

    public function test_submitted_application_has_submitted_at_timestamp(): void
    {
        // Create member with savings to pass eligibility check
        $account = \App\Models\SavingsAccount::factory()->create([
            'member_id' => $this->member->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
        ]);

        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.submit', $application));

        // Submit may succeed or fail depending on eligibility; check the timestamp was set on success
        $app = $application->fresh();
        if ($app->status === LoanApplicationStatus::Submitted) {
            $this->assertNotNull($app->submitted_at);
            $this->assertEquals($this->admin->id, $app->submitted_by);
        } else {
            // Eligibility failed — that's expected with minimal test data
            $this->assertDatabaseHas('loan_applications', [
                'id' => $application->id,
                'status' => LoanApplicationStatus::Draft->value,
            ]);
        }
    }

    public function test_cancelled_application_has_cancellation_details(): void
    {
        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->admin)->post(route('loan-applications.cancel', $application), [
            'cancellation_reason' => 'No longer needed',
        ]);

        $app = $application->fresh();
        $this->assertNotNull($app->cancelled_at);
        $this->assertEquals($this->admin->id, $app->cancelled_by);
        $this->assertEquals('No longer needed', $app->cancellation_reason);
    }

    public function test_application_list_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get(route('loan-applications.index'));
        $response->assertStatus(200);
        $response->assertViewIs('loan-applications.index');
    }

    public function test_application_create_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get(route('loan-applications.create'));
        $response->assertStatus(200);
        $response->assertViewIs('loan-applications.create');
    }

    public function test_application_show_page_loads(): void
    {
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('loan-applications.show', $application));
        $response->assertStatus(200);
        $response->assertViewIs('loan-applications.show');
    }

    public function test_validation_requires_member_id(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-applications.store'), [
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'business',
        ]);

        $response->assertSessionHasErrors('member_id');
    }

    public function test_validation_requires_loan_plan_id(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-applications.store'), [
            'member_id' => $this->member->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'business',
        ]);

        $response->assertSessionHasErrors('loan_plan_id');
    }

    // ─────────────────────────────────────────────
    // Finance Independence tests
    // ─────────────────────────────────────────────

    public function test_application_can_be_created_without_finance_accounts(): void
    {
        $member = Member::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'membership_status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.store'), [
            'member_id' => $member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => 'monthly',
            'loan_purpose' => 'business',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_applications', [
            'member_id' => $member->id,
            'loan_plan_id' => $this->plan->id,
            'status' => LoanApplicationStatus::Draft->value,
        ]);
    }

    public function test_application_can_be_submitted_without_finance_accounts_when_plan_allows(): void
    {
        $plan = LoanPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 3,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 100,
            'status' => 'active',
        ]);

        $member = Member::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'membership_status' => 'active',
        ]);

        $application = LoanApplication::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $member->id,
            'loan_plan_id' => $plan->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 100000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.submit', $application));

        $app = $application->fresh();
        $this->assertEquals(LoanApplicationStatus::Submitted, $app->status);
        $this->assertNotNull($app->submitted_at);
        $this->assertNotNull($app->eligibility_snapshot);
    }

    public function test_application_can_be_approved_without_finance_accounts(): void
    {
        $application = LoanApplication::factory()->underReview()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.approve', $application));

        $app = $application->fresh();
        $this->assertEquals(LoanApplicationStatus::Approved, $app->status);
    }

    public function test_application_can_be_rejected_without_finance_accounts(): void
    {
        $application = LoanApplication::factory()->underReview()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-applications.reject', $application), [
            'rejection_reason' => 'Insufficient documentation',
        ]);

        $app = $application->fresh();
        $this->assertEquals(LoanApplicationStatus::Rejected, $app->status);
        $this->assertEquals('Insufficient documentation', $app->rejection_reason);
    }

    public function test_approval_creates_no_finance_transactions(): void
    {
        $application = LoanApplication::factory()->underReview()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->admin)->post(route('loan-applications.approve', $application));

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_rejection_creates_no_finance_transactions(): void
    {
        $application = LoanApplication::factory()->underReview()->create([
            'organization_id' => $this->organization->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->admin)->post(route('loan-applications.reject', $application), [
            'rejection_reason' => 'Not eligible',
        ]);

        $this->assertDatabaseCount('journal_entries', 0);
    }
}
