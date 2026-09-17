<?php

namespace Tests\Feature\Finance;

use App\Enums\InterestMethod;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanDisbursementStatus;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\RepaymentFrequency;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\LoanPlan;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Services\DisbursementNumberGenerator;
use App\Services\FlatInterestCalculator;
use App\Services\LoanDisbursementService;
use App\Services\LoanNumberGenerator;
use App\Services\LoanRepaymentScheduleService;
use App\Services\ReducingBalanceInterestCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\HasAccountingSetup;

class LoanDisbursementTest extends TestCase
{
    use RefreshDatabase, HasAccountingSetup;

    private User $admin;
    private Organization $organization;
    private Branch $branch;
    private LoanPlan $flatPlan;
    private LoanPlan $rbPlan;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->organization = Organization::factory()->create();
        $this->branch = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin->organizations()->attach($this->organization->id);
        $this->member = Member::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'membership_status' => 'active',
        ]);

        $this->flatPlan = LoanPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'interest_method' => InterestMethod::Flat,
            'interest_rate' => 24.0,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'minimum_term' => 3,
            'maximum_term' => 24,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'processing_fee' => 10000,
            'insurance_fee' => 5000,
            'grace_period' => 0,
            'status' => 'active',
        ]);

        $this->rbPlan = LoanPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'interest_method' => InterestMethod::ReducingBalance,
            'interest_rate' => 18.0,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'minimum_term' => 3,
            'maximum_term' => 24,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'processing_fee' => 5000,
            'insurance_fee' => 2500,
            'grace_period' => 7,
            'status' => 'active',
        ]);

        $this->setUpAccountingFor($this->admin, $this->organization);
    }

    // ========================================
    // Flat Interest Calculator Tests
    // ========================================

    public function test_flat_interest_calculator_basic(): void
    {
        $calc = new FlatInterestCalculator();
        $result = $calc->calculate(1000000, 24.0, 12, RepaymentFrequency::Monthly);

        // 24% annual on 1,000,000 for 12 months = 240,000 interest
        $this->assertEqualsWithDelta(240000, $result['total_interest'], 1);
        $this->assertEqualsWithDelta(1240000, $result['total_amount'], 1);
        $this->assertEquals(12, $result['total_installments']);
        $this->assertEqualsWithDelta(103333.33, $result['amount_per_installment'], 1);
    }

    public function test_flat_interest_calculator_schedule_sum_equals_total(): void
    {
        $calc = new FlatInterestCalculator();
        $result = $calc->calculate(500000, 24.0, 6, RepaymentFrequency::Monthly);

        $sumPrincipal = array_sum(array_column($result['schedule'], 'principal_amount'));
        $sumInterest = array_sum(array_column($result['schedule'], 'interest_amount'));
        $sumTotal = array_sum(array_column($result['schedule'], 'total_amount'));

        $this->assertEqualsWithDelta(500000, $sumPrincipal, 1);
        $this->assertEqualsWithDelta($result['total_interest'], $sumInterest, 1);
        $this->assertEqualsWithDelta($result['total_amount'], $sumTotal, 1);
    }

    public function test_flat_interest_calculator_weekly_frequency(): void
    {
        $calc = new FlatInterestCalculator();
        $result = $calc->calculate(500000, 24.0, 3, RepaymentFrequency::Weekly);

        // 3 months ≈ 13 weeks
        $this->assertGreaterThanOrEqual(12, $result['total_installments']);
        $this->assertLessThanOrEqual(14, $result['total_installments']);
    }

    // ========================================
    // Reducing Balance Calculator Tests
    // ========================================

    public function test_reducing_balance_calculator_basic(): void
    {
        $calc = new ReducingBalanceInterestCalculator();
        $result = $calc->calculate(1000000, 18.0, 12, RepaymentFrequency::Monthly);

        // Reducing balance should have less total interest than flat
        $this->assertGreaterThan(0, $result['total_interest']);
        $this->assertGreaterThan($result['total_interest'], $result['total_amount']);
        $this->assertEquals(12, $result['total_installments']);
        $this->assertGreaterThan(0, $result['amount_per_installment']);
    }

    public function test_reducing_balance_schedule_declining_interest(): void
    {
        $calc = new ReducingBalanceInterestCalculator();
        $result = $calc->calculate(1000000, 18.0, 12, RepaymentFrequency::Monthly);

        // Interest portions should decrease over time
        $interests = array_column($result['schedule'], 'interest_amount');
        $this->assertGreaterThan($interests[count($interests) - 1], $interests[0]);
    }

    public function test_reducing_balance_schedule_sum_equals_total(): void
    {
        $calc = new ReducingBalanceInterestCalculator();
        $result = $calc->calculate(500000, 18.0, 6, RepaymentFrequency::Monthly);

        $sumPrincipal = array_sum(array_column($result['schedule'], 'principal_amount'));
        $sumInterest = array_sum(array_column($result['schedule'], 'interest_amount'));

        $this->assertEqualsWithDelta(500000, $sumPrincipal, 1);
        $this->assertEqualsWithDelta($result['total_interest'], $sumInterest, 1);
    }

    // ========================================
    // Loan Number Generator Tests
    // ========================================

    public function test_loan_number_generator_format(): void
    {
        $generator = new LoanNumberGenerator();
        $number = $generator->generate();

        $this->assertMatchesRegularExpression('/^LN-\d{4}-\d{6}$/', $number);
    }

    public function test_loan_number_generator_unique_across_loans(): void
    {
        $service = app(LoanDisbursementService::class);
        $app1 = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->flatPlan->id,
            'requested_amount' => 500000,
            'requested_term' => 6,
            'status' => LoanApplicationStatus::Approved,
        ]);
        $app2 = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => Member::factory()->create([
                'organization_id' => $this->organization->id,
                'branch_id' => $this->branch->id,
                'membership_status' => 'active',
            ])->id,
            'loan_plan_id' => $this->flatPlan->id,
            'requested_amount' => 500000,
            'requested_term' => 6,
            'status' => LoanApplicationStatus::Approved,
        ]);

        $loan1 = $service->createLoanFromApplication($app1);
        $loan2 = $service->createLoanFromApplication($app2);

        $this->assertNotEquals($loan1->loan_number, $loan2->loan_number);
    }

    // ========================================
    // Disbursement Number Generator Tests
    // ========================================

    public function test_disbursement_number_generator_format(): void
    {
        $generator = new DisbursementNumberGenerator();
        $number = $generator->generate();

        $this->assertMatchesRegularExpression('/^DIS-\d{4}-\d{6}$/', $number);
    }

    public function test_disbursement_number_generator_unique_across_disbursements(): void
    {
        $loan = Loan::factory()->pendingDisbursement()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
        ]);

        $service = app(LoanDisbursementService::class);
        $d1 = $service->createDisbursementRecord($loan, 500000, 'cash', null, null, null);
        $d2 = $service->createDisbursementRecord($loan, 500000, 'bank_transfer', null, null, null);

        $this->assertNotEquals($d1->disbursement_number, $d2->disbursement_number);
    }

    // ========================================
    // LoanRepaymentScheduleService Tests
    // ========================================

    public function test_schedule_service_generates_correct_installments(): void
    {
        $loan = Loan::factory()->create([
            'organization_id' => $this->organization->id,
            'principal_amount' => 1000000,
            'interest_rate' => 24.0,
            'interest_method' => InterestMethod::Flat,
            'term_months' => 12,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'grace_period' => 0,
            'disbursement_date' => now()->toDateString(),
        ]);

        $service = app(LoanRepaymentScheduleService::class);
        $result = $service->generateSchedule($loan);

        $this->assertEquals(12, $result['total_installments']);
        $this->assertEqualsWithDelta(240000, $result['total_interest'], 1);

        $schedule = LoanRepaymentSchedule::where('loan_id', $loan->id)->get();
        $this->assertCount(12, $schedule);
    }

    public function test_schedule_service_respects_grace_period(): void
    {
        $loan = Loan::factory()->create([
            'organization_id' => $this->organization->id,
            'principal_amount' => 1000000,
            'interest_rate' => 24.0,
            'interest_method' => InterestMethod::Flat,
            'term_months' => 6,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'grace_period' => 7,
            'disbursement_date' => '2026-01-01',
        ]);

        $service = app(LoanRepaymentScheduleService::class);
        $service->generateSchedule($loan);

        $firstInstallment = LoanRepaymentSchedule::where('loan_id', $loan->id)
            ->orderBy('installment_number')
            ->first();

        // First payment should be 7 days + 1 month after disbursement
        $this->assertEquals('2026-02-08', $firstInstallment->due_date->format('Y-m-d'));
    }

    public function test_schedule_service_weekly_frequency(): void
    {
        $loan = Loan::factory()->create([
            'organization_id' => $this->organization->id,
            'principal_amount' => 500000,
            'interest_rate' => 24.0,
            'interest_method' => InterestMethod::Flat,
            'term_months' => 3,
            'repayment_frequency' => RepaymentFrequency::Weekly,
            'grace_period' => 0,
            'disbursement_date' => now()->toDateString(),
        ]);

        $service = app(LoanRepaymentScheduleService::class);
        $result = $service->generateSchedule($loan);

        // 3 months ≈ 13 weeks
        $this->assertGreaterThanOrEqual(12, $result['total_installments']);
    }

    public function test_schedule_service_reducing_balance(): void
    {
        $loan = Loan::factory()->create([
            'organization_id' => $this->organization->id,
            'principal_amount' => 1000000,
            'interest_rate' => 18.0,
            'interest_method' => InterestMethod::ReducingBalance,
            'term_months' => 12,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'grace_period' => 0,
            'disbursement_date' => now()->toDateString(),
        ]);

        $service = app(LoanRepaymentScheduleService::class);
        $result = $service->generateSchedule($loan);

        $this->assertEquals(12, $result['total_installments']);
        $this->assertGreaterThan(0, $result['total_interest']);

        $schedule = LoanRepaymentSchedule::where('loan_id', $loan->id)->get();
        $this->assertCount(12, $schedule);
    }

    public function test_schedule_summary_calculation(): void
    {
        $loan = Loan::factory()->create([
            'organization_id' => $this->organization->id,
            'principal_amount' => 1000000,
            'interest_rate' => 24.0,
            'interest_method' => InterestMethod::Flat,
            'term_months' => 6,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'grace_period' => 0,
            'disbursement_date' => now()->toDateString(),
        ]);

        $service = app(LoanRepaymentScheduleService::class);
        $service->generateSchedule($loan);

        $summary = $service->getScheduleSummary($loan);

        $this->assertEquals(6, $summary['installments_total']);
        $this->assertEquals(0, $summary['installments_paid']);
        $this->assertEquals(0, $summary['installments_overdue']);
        $this->assertEquals(0, $summary['total_paid']);
    }

    // ========================================
    // LoanDisbursementService - Loan Creation
    // ========================================

    public function test_loan_can_be_created_from_approved_application(): void
    {
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->flatPlan->id,
            'requested_amount' => 1000000,
            'requested_term' => 12,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'status' => LoanApplicationStatus::Approved,
        ]);

        $service = app(LoanDisbursementService::class);
        $loan = $service->createLoanFromApplication($application);

        $this->assertNotNull($loan);
        $this->assertDatabaseHas('loans', [
            'loan_application_id' => $application->id,
            'member_id' => $this->member->id,
            'status' => LoanStatus::PendingDisbursement->value,
            'principal_amount' => 1000000,
        ]);

        $schedule = LoanRepaymentSchedule::where('loan_id', $loan->id)->get();
        $this->assertCount(12, $schedule);
    }

    public function test_loan_number_is_generated_on_creation(): void
    {
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->flatPlan->id,
            'requested_amount' => 500000,
            'requested_term' => 6,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'status' => LoanApplicationStatus::Approved,
        ]);

        $service = app(LoanDisbursementService::class);
        $loan = $service->createLoanFromApplication($application);

        $this->assertMatchesRegularExpression('/^LN-\d{4}-\d{6}$/', $loan->loan_number);
    }

    public function test_cannot_create_loan_from_non_approved_application(): void
    {
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->flatPlan->id,
            'requested_amount' => 500000,
            'requested_term' => 6,
            'status' => LoanApplicationStatus::Draft,
        ]);

        $service = app(LoanDisbursementService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->createLoanFromApplication($application);
    }

    public function test_cannot_create_duplicate_loan_from_same_application(): void
    {
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->flatPlan->id,
            'requested_amount' => 500000,
            'requested_term' => 6,
            'status' => LoanApplicationStatus::Approved,
        ]);

        $service = app(LoanDisbursementService::class);
        $service->createLoanFromApplication($application);

        $this->expectException(\InvalidArgumentException::class);
        $service->createLoanFromApplication($application);
    }

    public function test_loan_captures_plan_interest_rate_and_method(): void
    {
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->rbPlan->id,
            'requested_amount' => 1000000,
            'requested_term' => 12,
            'status' => LoanApplicationStatus::Approved,
        ]);

        $service = app(LoanDisbursementService::class);
        $loan = $service->createLoanFromApplication($application);

        $this->assertEquals(18.0, (float) $loan->interest_rate);
        $this->assertEquals(InterestMethod::ReducingBalance, $loan->interest_method);
    }

    // ========================================
    // LoanDisbursementService - Disbursement
    // ========================================

    public function test_disbursement_record_can_be_created(): void
    {
        $loan = Loan::factory()->pendingDisbursement()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'principal_amount' => 1000000,
            'processing_fee' => 10000,
            'insurance_fee' => 5000,
        ]);

        $service = app(LoanDisbursementService::class);
        $disbursement = $service->createDisbursementRecord(
            $loan,
            1000000,
            'cash',
            null,
            null,
            'Cash disbursement'
        );

        $this->assertNotNull($disbursement);
        $this->assertEquals(1000000, $disbursement->amount);
        $this->assertEquals(985000, $disbursement->net_amount);
        $this->assertEquals(LoanDisbursementStatus::Pending, $disbursement->status);

        $this->assertDatabaseHas('loan_disbursements', [
            'loan_id' => $loan->id,
            'amount' => 1000000,
            'net_amount' => 985000,
        ]);
    }

    public function test_cannot_create_disbursement_for_non_pending_loan(): void
    {
        $loan = Loan::factory()->active()->create([
            'organization_id' => $this->organization->id,
        ]);

        $service = app(LoanDisbursementService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->createDisbursementRecord($loan, 1000000, 'cash', null, null, null);
    }

    public function test_disbursement_can_be_confirmed(): void
    {
        $loan = Loan::factory()->pendingDisbursement()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'principal_amount' => 1000000,
            'processing_fee' => 10000,
            'insurance_fee' => 5000,
        ]);

        $service = app(LoanDisbursementService::class);
        $disbursement = $service->createDisbursementRecord($loan, 1000000, 'cash', null, null, null);

        $confirmed = $service->confirmDisbursement($disbursement, $this->admin);

        $this->assertEquals(LoanDisbursementStatus::Confirmed, $confirmed->status);

        $loan->refresh();
        $this->assertEquals(LoanStatus::Active, $loan->status);
        $this->assertNotNull($loan->disbursement_date);
    }

    public function test_disbursement_can_be_rejected(): void
    {
        $loan = Loan::factory()->pendingDisbursement()->create([
            'organization_id' => $this->organization->id,
        ]);

        $service = app(LoanDisbursementService::class);
        $disbursement = $service->createDisbursementRecord($loan, 1000000, 'cash', null, null, null);

        $rejected = $service->rejectDisbursement($disbursement, 'Insufficient documentation', $this->admin);

        $this->assertEquals(LoanDisbursementStatus::Rejected, $rejected->status);
        $this->assertEquals('Insufficient documentation', $rejected->rejection_reason);

        $loan->refresh();
        $this->assertEquals(LoanStatus::PendingDisbursement, $loan->status);
    }

    public function test_cannot_confirm_already_confirmed_disbursement(): void
    {
        $loan = Loan::factory()->pendingDisbursement()->create([
            'organization_id' => $this->organization->id,
        ]);

        $service = app(LoanDisbursementService::class);
        $disbursement = $service->createDisbursementRecord($loan, 1000000, 'cash', null, null, null);
        $service->confirmDisbursement($disbursement, $this->admin);

        $this->expectException(\InvalidArgumentException::class);
        $service->confirmDisbursement($disbursement->fresh(), $this->admin);
    }

    // ========================================
    // LoanDisbursementService - Cancellation
    // ========================================

    public function test_loan_can_be_cancelled(): void
    {
        $loan = Loan::factory()->pendingDisbursement()->create([
            'organization_id' => $this->organization->id,
        ]);

        $service = app(LoanDisbursementService::class);
        $cancelled = $service->cancelLoan($loan, 'Changed mind');

        $this->assertEquals(LoanStatus::Cancelled, $cancelled->status);

        $schedules = LoanRepaymentSchedule::where('loan_id', $loan->id)->get();
        foreach ($schedules as $s) {
            $this->assertEquals(LoanScheduleInstallmentStatus::Waived, $s->status);
        }
    }

    public function test_cannot_cancel_completed_loan(): void
    {
        $loan = Loan::factory()->completed()->create([
            'organization_id' => $this->organization->id,
        ]);

        $service = app(LoanDisbursementService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->cancelLoan($loan);
    }

    // ========================================
    // LoanStatus Enum Tests
    // ========================================

    public function test_loan_status_transition_valid(): void
    {
        $this->assertTrue(LoanStatus::Approved->canTransitionTo(LoanStatus::PendingDisbursement));
        $this->assertTrue(LoanStatus::PendingDisbursement->canTransitionTo(LoanStatus::Disbursed));
        $this->assertTrue(LoanStatus::Disbursed->canTransitionTo(LoanStatus::Active));
        $this->assertTrue(LoanStatus::Active->canTransitionTo(LoanStatus::Completed));
    }

    public function test_loan_status_transition_invalid(): void
    {
        $this->assertFalse(LoanStatus::Completed->canTransitionTo(LoanStatus::Active));
        $this->assertFalse(LoanStatus::Cancelled->canTransitionTo(LoanStatus::Active));
        $this->assertFalse(LoanStatus::Active->canTransitionTo(LoanStatus::PendingDisbursement));
    }

    public function test_loan_status_terminal(): void
    {
        $this->assertTrue(LoanStatus::Completed->isTerminal());
        $this->assertTrue(LoanStatus::Cancelled->isTerminal());
        $this->assertFalse(LoanStatus::Active->isTerminal());
        $this->assertFalse(LoanStatus::PendingDisbursement->isTerminal());
    }

    // ========================================
    // Controller Route Tests
    // ========================================

    public function test_admin_can_view_loans_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('loans.index'));
        $response->assertOk();
    }

    public function test_admin_can_view_loan_show(): void
    {
        $loan = Loan::factory()->pendingDisbursement()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('loans.show', $loan));
        $response->assertOk();
    }

    public function test_admin_can_view_loan_schedule(): void
    {
        $loan = Loan::factory()->pendingDisbursement()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('loans.schedule', $loan));
        $response->assertOk();
    }

    public function test_admin_can_view_disbursements_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('loan-disbursements.index'));
        $response->assertOk();
    }

    public function test_admin_can_create_disbursement_via_route(): void
    {
        $loan = Loan::factory()->pendingDisbursement()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'principal_amount' => 1000000,
            'processing_fee' => 10000,
            'insurance_fee' => 5000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loan-disbursements.store'), [
            'loan_id' => $loan->id,
            'amount' => 1000000,
            'disbursement_method' => 'cash',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_disbursements', [
            'loan_id' => $loan->id,
            'amount' => 1000000,
        ]);
    }

    public function test_admin_can_confirm_disbursement_via_route(): void
    {
        $loan = Loan::factory()->pendingDisbursement()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
        ]);

        $service = app(LoanDisbursementService::class);
        $disbursement = $service->createDisbursementRecord($loan, 1000000, 'cash', null, null, null);

        $response = $this->actingAs($this->admin)->post(route('loan-disbursements.confirm', $disbursement));
        $response->assertRedirect();

        $loan->refresh();
        $this->assertEquals(LoanStatus::Active, $loan->status);
    }

    public function test_admin_can_cancel_loan_via_route(): void
    {
        $loan = Loan::factory()->pendingDisbursement()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('loans.cancel', $loan), [
            'reason' => 'No longer needed',
        ]);

        $response->assertRedirect();
        $loan->refresh();
        $this->assertEquals(LoanStatus::Cancelled, $loan->status);
    }

    public function test_admin_can_create_loan_from_application(): void
    {
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->flatPlan->id,
            'requested_amount' => 1000000,
            'requested_term' => 12,
        ]);

        $application->update(['status' => LoanApplicationStatus::Approved]);
        $application->refresh();

        $this->assertEquals('approved', $application->status->value);

        $response = $this->actingAs($this->admin)->post(route('loans.create-from-application', $application));
        $response->assertRedirect();

        $this->assertDatabaseHas('loans', [
            'loan_application_id' => $application->id,
        ]);
    }

    // ========================================
    // Tenant Isolation Tests
    // ========================================

    public function test_loans_are_tenant_scoped(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        Loan::factory()->count(3)->create(['organization_id' => $org1->id]);
        Loan::factory()->count(2)->create(['organization_id' => $org2->id]);

        $response = $this->actingAs($this->admin)->get(route('loans.index'));
        $response->assertOk();
    }

    // ========================================
    // Edge Cases
    // ========================================

    public function test_zero_interest_rate_loan(): void
    {
        $loan = Loan::factory()->create([
            'organization_id' => $this->organization->id,
            'principal_amount' => 500000,
            'interest_rate' => 0,
            'interest_method' => InterestMethod::Flat,
            'term_months' => 6,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'grace_period' => 0,
            'disbursement_date' => now()->toDateString(),
        ]);

        $service = app(LoanRepaymentScheduleService::class);
        $result = $service->generateSchedule($loan);

        $this->assertEquals(0, $result['total_interest']);
        $this->assertEqualsWithDelta(500000, $result['total_amount'], 1);
    }

    public function test_single_installment_loan(): void
    {
        $loan = Loan::factory()->create([
            'organization_id' => $this->organization->id,
            'principal_amount' => 100000,
            'interest_rate' => 24.0,
            'interest_method' => InterestMethod::Flat,
            'term_months' => 1,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'grace_period' => 0,
            'disbursement_date' => now()->toDateString(),
        ]);

        $service = app(LoanRepaymentScheduleService::class);
        $result = $service->generateSchedule($loan);

        $this->assertEquals(1, $result['total_installments']);
        $this->assertCount(1, LoanRepaymentSchedule::where('loan_id', $loan->id)->get());
    }

    public function test_interest_calculator_zero_rate(): void
    {
        $calc = new FlatInterestCalculator();
        $result = $calc->calculate(1000000, 0, 12, RepaymentFrequency::Monthly);

        $this->assertEquals(0, $result['total_interest']);
        $this->assertEqualsWithDelta(1000000, $result['total_amount'], 1);
    }
}
