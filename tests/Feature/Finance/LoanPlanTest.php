<?php

namespace Tests\Feature\Finance;

use App\Enums\LoanPlanStatus;
use App\Models\LoanPlan;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->organization = Organization::factory()->create();
    }

    public function test_loan_plan_can_be_created(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-plans.store'), [
            'name' => 'Business Loan',
            'code' => 'LPN-BUS-001',
            'organization_id' => $this->organization->id,
            'loan_purpose' => 'business',
            'minimum_amount' => 100000,
            'maximum_amount' => 10000000,
            'interest_rate' => 2.5,
            'interest_method' => 'flat',
            'minimum_term' => 3,
            'maximum_term' => 36,
            'repayment_frequency' => 'monthly',
            'maximum_active_loans' => 2,
            'requires_guarantor' => true,
            'minimum_guarantors' => 2,
            'requires_collateral' => true,
            'minimum_savings_balance' => 500000,
            'savings_multiplier' => 4,
            'share_multiplier' => 3,
            'maximum_loan_to_savings_ratio' => 5,
            'grace_period' => 7,
            'processing_fee' => 5000,
            'insurance_fee' => 2000,
            'late_payment_allowed' => true,
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_plans', [
            'name' => 'Business Loan',
            'code' => 'LPN-BUS-001',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_loan_plan_can_be_updated(): void
    {
        $plan = LoanPlan::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->put(route('loan-plans.update', $plan), [
            'name' => 'Updated Business Loan',
            'code' => $plan->code,
            'organization_id' => $this->organization->id,
            'loan_purpose' => 'business',
            'minimum_amount' => 200000,
            'maximum_amount' => 20000000,
            'interest_rate' => 3.0,
            'interest_method' => 'flat',
            'minimum_term' => 6,
            'maximum_term' => 48,
            'repayment_frequency' => 'monthly',
            'maximum_active_loans' => 1,
            'requires_guarantor' => false,
            'minimum_guarantors' => 0,
            'requires_collateral' => false,
            'minimum_savings_balance' => 200000,
            'savings_multiplier' => 3,
            'share_multiplier' => 2,
            'maximum_loan_to_savings_ratio' => 4,
            'grace_period' => 14,
            'processing_fee' => 10000,
            'insurance_fee' => 5000,
            'late_payment_allowed' => false,
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_plans', [
            'id' => $plan->id,
            'name' => 'Updated Business Loan',
        ]);
    }

    public function test_loan_plan_can_be_deleted(): void
    {
        $plan = LoanPlan::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->delete(route('loan-plans.destroy', $plan));

        $response->assertRedirect();
        $this->assertSoftDeleted('loan_plans', ['id' => $plan->id]);
    }

    public function test_loan_plan_can_be_activated(): void
    {
        $plan = LoanPlan::factory()->inactive()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->post(route('loan-plans.activate', $plan));

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_plans', [
            'id' => $plan->id,
            'status' => LoanPlanStatus::Active,
        ]);
    }

    public function test_loan_plan_can_be_deactivated(): void
    {
        $plan = LoanPlan::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->post(route('loan-plans.deactivate', $plan));

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_plans', [
            'id' => $plan->id,
            'status' => LoanPlanStatus::Inactive,
        ]);
    }

    public function test_loan_plan_index_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get(route('loan-plans.index'));

        $response->assertStatus(200);
        $response->assertViewIs('loan-plans.index');
    }

    public function test_loan_plan_create_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get(route('loan-plans.create'));

        $response->assertStatus(200);
        $response->assertViewIs('loan-plans.create');
    }

    public function test_loan_plan_show_page_loads(): void
    {
        $plan = LoanPlan::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->get(route('loan-plans.show', $plan));

        $response->assertStatus(200);
        $response->assertViewIs('loan-plans.show');
    }

    public function test_loan_plan_edit_page_loads(): void
    {
        $plan = LoanPlan::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->get(route('loan-plans.edit', $plan));

        $response->assertStatus(200);
        $response->assertViewIs('loan-plans.edit');
    }

    public function test_loan_plan_code_must_be_unique_per_organization(): void
    {
        LoanPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'LPN-001',
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-plans.store'), [
            'name' => 'Duplicate Loan Plan',
            'code' => 'LPN-001',
            'organization_id' => $this->organization->id,
            'loan_purpose' => 'business',
            'minimum_amount' => 100000,
            'maximum_amount' => 10000000,
            'interest_rate' => 2.5,
            'interest_method' => 'flat',
            'minimum_term' => 3,
            'maximum_term' => 36,
            'repayment_frequency' => 'monthly',
            'maximum_active_loans' => 1,
            'minimum_savings_balance' => 100000,
            'savings_multiplier' => 3,
            'share_multiplier' => 2,
            'maximum_loan_to_savings_ratio' => 5,
            'grace_period' => 0,
            'processing_fee' => 0,
            'insurance_fee' => 0,
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_maximum_amount_must_be_greater_than_minimum(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-plans.store'), [
            'name' => 'Invalid Range',
            'code' => 'LPN-INVALID',
            'organization_id' => $this->organization->id,
            'loan_purpose' => 'business',
            'minimum_amount' => 1000000,
            'maximum_amount' => 500000,
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

        $response->assertSessionHasErrors('maximum_amount');
    }

    public function test_interest_rate_cannot_exceed_100_percent(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-plans.store'), [
            'name' => 'High Interest',
            'code' => 'LPN-HIGH',
            'organization_id' => $this->organization->id,
            'loan_purpose' => 'business',
            'minimum_amount' => 100000,
            'maximum_amount' => 10000000,
            'interest_rate' => 150,
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

        $response->assertSessionHasErrors('interest_rate');
    }

    public function test_maximum_term_must_be_gte_minimum_term(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-plans.store'), [
            'name' => 'Invalid Term',
            'code' => 'LPN-TERM',
            'organization_id' => $this->organization->id,
            'loan_purpose' => 'business',
            'minimum_amount' => 100000,
            'maximum_amount' => 10000000,
            'interest_rate' => 2.5,
            'interest_method' => 'flat',
            'minimum_term' => 24,
            'maximum_term' => 6,
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

        $response->assertSessionHasErrors('maximum_term');
    }

    public function test_required_fields_must_be_present(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-plans.store'), []);

        $response->assertSessionHasErrors([
            'name',
            'code',
            'organization_id',
            'loan_purpose',
            'minimum_amount',
            'maximum_amount',
            'interest_rate',
            'interest_method',
            'minimum_term',
            'maximum_term',
            'repayment_frequency',
            'maximum_active_loans',
            'grace_period',
            'processing_fee',
            'insurance_fee',
        ]);
    }

    public function test_search_filter_works(): void
    {
        LoanPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Emergency Loan Plan',
        ]);

        LoanPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Business Loan Plan',
        ]);

        $response = $this->actingAs($this->admin)->get(route('loan-plans.index', ['search' => 'Emergency']));

        $response->assertStatus(200);
    }

    public function test_status_filter_works(): void
    {
        LoanPlan::factory()->create(['organization_id' => $this->organization->id, 'status' => LoanPlanStatus::Active]);
        LoanPlan::factory()->inactive()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->get(route('loan-plans.index', ['status' => 'active']));

        $response->assertStatus(200);
    }

    public function test_organization_filter_works(): void
    {
        $org2 = Organization::factory()->create();
        LoanPlan::factory()->create(['organization_id' => $this->organization->id]);
        LoanPlan::factory()->create(['organization_id' => $org2->id]);

        $response = $this->actingAs($this->admin)->get(route('loan-plans.index', ['organization_id' => $this->organization->id]));

        $response->assertStatus(200);
    }

    public function test_loan_plan_purpose_filter_works(): void
    {
        LoanPlan::factory()->create(['organization_id' => $this->organization->id, 'loan_purpose' => 'business']);
        LoanPlan::factory()->create(['organization_id' => $this->organization->id, 'loan_purpose' => 'agriculture']);

        $response = $this->actingAs($this->admin)->get(route('loan-plans.index', ['loan_purpose' => 'business']));

        $response->assertStatus(200);
    }
}
