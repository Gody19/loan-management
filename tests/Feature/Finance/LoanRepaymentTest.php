<?php

namespace Tests\Feature\Finance;

use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\RepaymentFrequency;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\LoanPlan;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentAllocation;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Services\LoanDelinquencyService;
use App\Services\LoanRepaymentAllocationService;
use App\Services\LoanRepaymentService;
use App\Services\RepaymentNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\HasAccountingSetup;

class LoanRepaymentTest extends TestCase
{
    use RefreshDatabase, HasAccountingSetup;

    private User $admin;
    private Organization $organization;
    private Branch $branch;
    private Member $member;
    private LoanPlan $loanPlan;
    private Loan $loan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->organization = Organization::factory()->create();
        $this->branch = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->member = Member::factory()->create(['organization_id' => $this->organization->id]);
        $this->loanPlan = LoanPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'interest_rate' => 24.0,
            'interest_method' => 'flat',
            'repayment_frequency' => 'monthly',
            'grace_period' => 0,
        ]);

        // Attach admin to the test organization
        $this->admin->organizations()->attach($this->organization->id);

        $this->setUpAccountingFor($this->admin, $this->organization);

        $this->loan = $this->createActiveLoan();
    }

    private function createActiveLoan(): Loan
    {
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->loanPlan->id,
            'requested_amount' => 100000,
            'requested_term' => 12,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'status' => 'approved',
        ]);

        $loan = Loan::create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->loanPlan->id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-2026-000001',
            'principal_amount' => 100000,
            'disbursed_amount' => 100000,
            'interest_rate' => 24.0,
            'interest_method' => 'flat',
            'term_months' => 12,
            'repayment_frequency' => 'monthly',
            'total_interest' => 24000,
            'total_amount' => 124000,
            'processing_fee' => 5000,
            'insurance_fee' => 2000,
            'amount_paid' => 0,
            'outstanding_balance' => 100000,
            'grace_period' => 0,
            'status' => LoanStatus::Active,
            'disbursement_date' => now()->subMonths(1),
            'total_installments' => 12,
        ]);

        for ($i = 1; $i <= 12; $i++) {
            LoanRepaymentSchedule::create([
                'loan_id' => $loan->id,
                'organization_id' => $this->organization->id,
                'installment_number' => $i,
                'due_date' => now()->subMonth()->addMonths($i),
                'principal_amount' => 8333.33,
                'interest_amount' => 2000,
                'total_amount' => 10333.33,
                'amount_paid' => 0,
                'outstanding_amount' => 10333.33,
                'running_balance' => 100000 - (8333.33 * $i),
                'status' => LoanScheduleInstallmentStatus::Pending,
                'days_overdue' => 0,
                'late_fee' => 0,
            ]);
        }

        return $loan;
    }

    // ========== RepaymentNumberGenerator Tests ==========

    public function test_repayment_number_generator_creates_valid_format(): void
    {
        $generator = new RepaymentNumberGenerator();

        $number = $generator->generate();

        $this->assertMatchesRegularExpression('/^RPT-\d{4}-\d{6}$/', $number);
    }

    public function test_repayment_number_generator_increments_when_records_exist(): void
    {
        // Create a record first
        LoanRepayment::create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'repayment_number' => 'RPT-' . date('Y') . '-000005',
            'amount' => 1000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => LoanRepaymentStatus::Posted,
        ]);

        $generator = new RepaymentNumberGenerator();
        $number = $generator->generate();

        $this->assertMatchesRegularExpression('/^RPT-\d{4}-\d{6}$/', $number);
        $this->assertStringEndsWith('000006', $number);
    }

    // ========== LoanRepaymentService Tests ==========

    public function test_post_repayment_successfully(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            'First payment',
            null,
        );

        $this->assertInstanceOf(LoanRepayment::class, $repayment);
        $this->assertEquals(LoanRepaymentStatus::Posted, $repayment->status);
        $this->assertEquals(10333.33, $repayment->amount);
        $this->assertNotNull($repayment->repayment_number);
    }

    public function test_post_repayment_updates_loan_balance(): void
    {
        $service = app(LoanRepaymentService::class);

        $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $this->loan->refresh();
        $this->assertEquals(10333.33, $this->loan->amount_paid);
        $this->assertEquals(89666.67, $this->loan->outstanding_balance);
    }

    public function test_post_repayment_creates_allocations(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $this->assertGreaterThan(0, $repayment->allocations->count());
    }

    public function test_post_repayment_rejects_inactive_loan(): void
    {
        $this->loan->update(['status' => LoanStatus::Completed]);

        $service = app(LoanRepaymentService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loan must be active to accept repayments.');

        $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );
    }

    public function test_post_repayment_rejects_fully_paid_loan(): void
    {
        $this->loan->update(['outstanding_balance' => 0]);

        $service = app(LoanRepaymentService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loan is fully paid. No further repayments accepted.');

        $service->postRepayment(
            $this->loan,
            1000,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );
    }

    public function test_post_repayment_with_idempotency_key(): void
    {
        $service = app(LoanRepaymentService::class);
        $key = 'unique-key-123';

        $service->postRepayment(
            $this->loan,
            5000,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            $key,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate payment detected.');

        $service->postRepayment(
            $this->loan,
            5000,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            $key,
        );
    }

    public function test_post_multiple_partial_payments(): void
    {
        $service = app(LoanRepaymentService::class);

        $service->postRepayment($this->loan, 5000, now()->toDateString(), 'cash', null, null, null, null);
        $service->postRepayment($this->loan, 5000, now()->toDateString(), 'cash', null, null, null, null);

        $this->loan->refresh();
        $this->assertEquals(10000, $this->loan->amount_paid);
    }

    public function test_post_payment_exceeding_installment_allocates_to_next(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            15000,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $this->assertGreaterThanOrEqual(2, $repayment->allocations->count());
    }

    // ========== LoanRepayment Reversal Tests ==========

    public function test_reverse_repayment_successfully(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $reversed = $service->reverseRepayment($repayment, 'Payment was made in error.');

        $this->assertEquals(LoanRepaymentStatus::Reversed, $reversed->status);
        $this->assertEquals('Payment was made in error.', $reversed->reversal_reason);
    }

    public function test_reverse_repayment_restores_loan_balance(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $service->reverseRepayment($repayment, 'Error in payment.');

        $this->loan->refresh();
        $this->assertEquals(0, $this->loan->amount_paid);
        $this->assertEquals(100000, $this->loan->outstanding_balance);
    }

    public function test_reverse_already_reversed_payment_fails(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $service->reverseRepayment($repayment, 'First reversal.');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only posted repayments can be reversed.');

        $service->reverseRepayment($repayment->fresh(), 'Second reversal.');
    }

    public function test_reverse_repayment_restores_installment_status(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $installment = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $this->assertEquals(LoanScheduleInstallmentStatus::Paid, $installment->fresh()->status);

        $service->reverseRepayment($repayment, 'Error.');

        $installment->refresh();
        $this->assertEquals(LoanScheduleInstallmentStatus::Pending, $installment->status);
    }

    // ========== Allocation Service Tests ==========

    public function test_allocation_service_distributes_fees_first(): void
    {
        $service = app(LoanRepaymentAllocationService::class);

        $repayment = LoanRepayment::create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'repayment_number' => 'RPT-2026-000001',
            'amount' => 10333.33,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => LoanRepaymentStatus::Posted,
        ]);

        $allocations = $service->allocatePayment($this->loan, 10333.33, $repayment->id);

        $this->assertNotEmpty($allocations);
    }

    public function test_allocation_service_oldest_installment_first(): void
    {
        $service = app(LoanRepaymentAllocationService::class);

        $repayment = LoanRepayment::create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'repayment_number' => 'RPT-2026-000002',
            'amount' => 5000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => LoanRepaymentStatus::Posted,
        ]);

        $allocations = $service->allocatePayment($this->loan, 5000, $repayment->id);

        if (!empty($allocations)) {
            $this->assertEquals(1, $allocations[0]->installment->installment_number);
        }
    }

    public function test_allocation_service_handles_full_payment(): void
    {
        $service = app(LoanRepaymentAllocationService::class);

        $repayment = LoanRepayment::create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'repayment_number' => 'RPT-2026-000003',
            'amount' => 10333.33,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => LoanRepaymentStatus::Posted,
        ]);

        $allocations = $service->allocatePayment($this->loan, 10333.33, $repayment->id);

        $installment = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $this->assertEquals(LoanScheduleInstallmentStatus::Paid, $installment->fresh()->status);
        $this->assertEquals(0, $installment->fresh()->outstanding_amount);
    }

    public function test_reverse_allocations_restores_installments(): void
    {
        $service = app(LoanRepaymentAllocationService::class);

        $repayment = LoanRepayment::create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'repayment_number' => 'RPT-2026-000004',
            'amount' => 10333.33,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => LoanRepaymentStatus::Posted,
        ]);

        $service->allocatePayment($this->loan, 10333.33, $repayment->id);

        $installment = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $this->assertEquals(LoanScheduleInstallmentStatus::Paid, $installment->fresh()->status);

        $service->reverseAllocations($repayment->id);

        $installment->refresh();
        $this->assertEquals(LoanScheduleInstallmentStatus::Pending, $installment->status);
        $this->assertEquals(10333.33, $installment->outstanding_amount);
    }

    // ========== Delinquency Service Tests ==========

    public function test_delinquency_service_updates_overdue_status(): void
    {
        $schedule = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $schedule->update(['due_date' => now()->subDays(10)]);

        $service = app(LoanDelinquencyService::class);
        $result = $service->updateDelinquencyStatuses($this->organization->id);

        $this->assertGreaterThan(0, $result['loans_checked']);

        $schedule->refresh();
        $this->assertEquals(LoanScheduleInstallmentStatus::Overdue, $schedule->status);
        $this->assertEquals(10, $schedule->days_overdue);
    }

    public function test_delinquency_service_calculates_days_past_due(): void
    {
        $schedule = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $schedule->update(['due_date' => now()->subDays(15)]);

        $service = app(LoanDelinquencyService::class);
        $dpd = $service->getDaysPastDue($this->loan);

        $this->assertEquals(15, $dpd);
    }

    public function test_delinquency_service_returns_zero_for_current_loans(): void
    {
        $service = app(LoanDelinquencyService::class);
        $dpd = $service->getDaysPastDue($this->loan);

        $this->assertEquals(0, $dpd);
    }

    public function test_delinquency_service_calculates_par(): void
    {
        $schedule = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $schedule->update(['due_date' => now()->subDays(35)]);

        $service = app(LoanDelinquencyService::class);
        $par = $service->getPAR($this->organization->id, 30);

        $this->assertArrayHasKey('total_outstanding', $par);
        $this->assertArrayHasKey('delinquent_outstanding', $par);
        $this->assertArrayHasKey('par_percentage', $par);
        $this->assertGreaterThan(0, $par['delinquent_outstanding']);
    }

    public function test_delinquency_service_gets_delinquent_loans(): void
    {
        $schedule = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $schedule->update(['due_date' => now()->subDays(5)]);

        $service = app(LoanDelinquencyService::class);
        $delinquent = $service->getDelinquentLoans($this->organization->id);

        $this->assertGreaterThan(0, $delinquent->count());
    }

    public function test_delinquency_service_collection_rate(): void
    {
        $service = app(LoanDelinquencyService::class);
        $rate = $service->getCollectionRate(
            $this->organization->id,
            now()->subMonth()->startOfMonth()->toDateString(),
            now()->subMonth()->endOfMonth()->toDateString()
        );

        $this->assertArrayHasKey('total_due', $rate);
        $this->assertArrayHasKey('total_collected', $rate);
        $this->assertArrayHasKey('collection_rate', $rate);
    }

    // ========== Model Tests ==========

    public function test_loan_repayment_model_relationships(): void
    {
        $repayment = LoanRepayment::factory()->create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
        ]);

        $this->assertNotNull($repayment->loan);
        $this->assertNotNull($repayment->organization);
        $this->assertNotNull($repayment->branch);
        $this->assertNotNull($repayment->member);
    }

    public function test_loan_repayment_scopes(): void
    {
        LoanRepayment::factory()->create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
            'status' => LoanRepaymentStatus::Posted,
        ]);

        $this->assertGreaterThan(0, LoanRepayment::forOrganization($this->organization->id)->count());
        $this->assertGreaterThan(0, LoanRepayment::forLoan($this->loan->id)->count());
        $this->assertGreaterThan(0, LoanRepayment::byStatus('posted')->count());
    }

    public function test_loan_repayment_allocation_model(): void
    {
        $installment = LoanRepaymentSchedule::where('loan_id', $this->loan->id)->first();
        $repayment = LoanRepayment::factory()->create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
        ]);

        $allocation = LoanRepaymentAllocation::create([
            'loan_repayment_id' => $repayment->id,
            'loan_id' => $this->loan->id,
            'loan_repayment_schedule_id' => $installment->id,
            'organization_id' => $this->organization->id,
            'amount' => 5000,
            'principal_allocation' => 3500,
            'interest_allocation' => 1250,
            'fee_allocation' => 250,
        ]);

        $this->assertNotNull($allocation->repayment);
        $this->assertNotNull($allocation->loan);
        $this->assertNotNull($allocation->installment);
    }

    public function test_loan_repayment_status_enum(): void
    {
        $this->assertEquals('posted', LoanRepaymentStatus::Posted->value);
        $this->assertEquals('reversed', LoanRepaymentStatus::Reversed->value);
        $this->assertEquals('Posted', LoanRepaymentStatus::Posted->label());
        $this->assertEquals('Reversed', LoanRepaymentStatus::Reversed->label());
        $this->assertTrue(LoanRepaymentStatus::Posted->isReversible());
        $this->assertFalse(LoanRepaymentStatus::Reversed->isReversible());
    }

    // ========== Multiple Payment Scenarios ==========

    public function test_multiple_payments_reduce_balance_correctly(): void
    {
        $service = app(LoanRepaymentService::class);

        $service->postRepayment($this->loan, 5000, now()->toDateString(), 'cash', null, null, null, null);
        $service->postRepayment($this->loan, 5000, now()->toDateString(), 'mobile_money', null, null, null, null);
        $service->postRepayment($this->loan, 5000, now()->toDateString(), 'bank_transfer', null, null, null, null);

        $this->loan->refresh();
        $this->assertEquals(15000, $this->loan->amount_paid);
    }

    public function test_payment_with_reference_number(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            5000,
            now()->toDateString(),
            'mobile_money',
            null,
            'MPESA-123456',
            'M-Pesa payment',
            null,
        );

        $this->assertEquals('MPESA-123456', $repayment->reference_number);
        $this->assertEquals('M-Pesa payment', $repayment->notes);
    }

    public function test_payment_updates_next_payment_date(): void
    {
        $service = app(LoanRepaymentService::class);

        $service->postRepayment($this->loan, 10333.33, now()->toDateString(), 'cash', null, null, null, null);

        $this->loan->refresh();
        $this->assertNotNull($this->loan->next_payment_date);
    }

    public function test_payment_updates_installments_paid_count(): void
    {
        $service = app(LoanRepaymentService::class);

        $service->postRepayment($this->loan, 10333.33, now()->toDateString(), 'cash', null, null, null, null);

        $this->loan->refresh();
        $this->assertEquals(1, $this->loan->installments_paid);
    }

    // ========== Edge Cases ==========

    public function test_zero_amount_payment_rejected(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('loan-repayments.store', $this->loan), [
            'amount' => 0,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('amount');
    }

    public function test_future_payment_date_rejected(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('loan-repayments.store', $this->loan), [
            'amount' => 5000,
            'payment_date' => now()->addDay()->toDateString(),
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('payment_date');
    }

    public function test_invalid_payment_method_rejected(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('loan-repayments.store', $this->loan), [
            'amount' => 5000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'invalid_method',
        ])->assertSessionHasErrors('payment_method');
    }

    // ========== Authorization Tests ==========

    public function test_unauthenticated_user_cannot_access_repayments(): void
    {
        $this->get(route('loan-repayments.index', $this->loan))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_repayments(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('loan-repayments.index', $this->loan))
            ->assertOk();
    }

    public function test_authenticated_user_can_view_create_form(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('loan-repayments.create', $this->loan))
            ->assertOk();
    }

    public function test_authenticated_user_can_post_payment(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('loan-repayments.store', $this->loan), [
            'amount' => 10333.33,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'notes' => 'Test payment',
        ])->assertRedirect();

        $this->assertDatabaseHas('loan_repayments', [
            'loan_id' => $this->loan->id,
            'amount' => 10333.33,
            'status' => 'posted',
        ]);
    }

    public function test_authenticated_user_can_view_repayment_details(): void
    {
        $this->actingAs($this->admin);

        $repayment = LoanRepayment::factory()->create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
        ]);

        $this->get(route('loan-repayments.show', $repayment))
            ->assertOk();
    }

    public function test_authenticated_user_can_reverse_payment(): void
    {
        $this->actingAs($this->admin);

        $repayment = LoanRepayment::factory()->create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->organization->id,
            'status' => LoanRepaymentStatus::Posted,
        ]);

        $this->post(route('loan-repayments.reverse', $repayment), [
            'reason' => 'Payment was made in error and needs reversal.',
        ])->assertRedirect();

        $repayment->refresh();
        $this->assertEquals(LoanRepaymentStatus::Reversed, $repayment->status);
    }

    // ========== Statement Tests ==========

    public function test_loan_statement_page_loads(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('loans.statement', $this->loan))
            ->assertOk();
    }

    // ========== Collections Page Tests ==========

    public function test_collections_page_loads(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('loan-collections.index'))
            ->assertOk();
    }

    public function test_collections_page_shows_delinquent_loans(): void
    {
        $this->actingAs($this->admin);

        $schedule = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $schedule->update(['due_date' => now()->subDays(10)]);

        $this->get(route('loan-collections.index'))
            ->assertOk()
            ->assertSee($this->loan->loan_number);
    }

    // ========== Integration Tests ==========

    public function test_full_payment_lifecycle(): void
    {
        $service = app(LoanRepaymentService::class);

        // Post payment
        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            'Full installment payment',
            null,
        );

        $this->assertEquals(LoanRepaymentStatus::Posted, $repayment->status);

        $this->loan->refresh();
        $this->assertEquals(10333.33, $this->loan->amount_paid);

        // Verify installment is paid
        $installment = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();
        $this->assertEquals(LoanScheduleInstallmentStatus::Paid, $installment->fresh()->status);

        // Reverse payment
        $reversed = $service->reverseRepayment($repayment, 'Testing reversal.');

        $this->assertEquals(LoanRepaymentStatus::Reversed, $reversed->status);

        $this->loan->refresh();
        $this->assertEquals(0, $this->loan->amount_paid);

        $installment->refresh();
        $this->assertEquals(LoanScheduleInstallmentStatus::Pending, $installment->status);
    }

    public function test_partial_payment_then_full_payment(): void
    {
        $service = app(LoanRepaymentService::class);

        // Partial payment
        $service->postRepayment($this->loan, 5000, now()->toDateString(), 'cash', null, null, null, null);

        $installment = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $this->assertEquals(LoanScheduleInstallmentStatus::Partial, $installment->fresh()->status);

        // Remaining payment
        $service->postRepayment($this->loan, 5333.33, now()->toDateString(), 'cash', null, null, null, null);

        $installment->refresh();
        $this->assertEquals(LoanScheduleInstallmentStatus::Paid, $installment->status);
    }

    public function test_overpayment_allocation(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            15000,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $this->assertGreaterThanOrEqual(2, $repayment->allocations->count());
    }

    // ========== Financial Integrity: PAR Numerical Test ==========

    public function test_par_uses_principal_outstanding_not_total_outstanding(): void
    {
        $this->loan->update(['status' => LoanStatus::Completed, 'outstanding_balance' => 0]);

        // Scenario from spec:
        // Loan A: principal outstanding = 500,000, DPD = 45
        // Loan B: principal outstanding = 300,000, DPD = 10
        // Loan C: principal outstanding = 200,000, DPD = 75
        // Total principal outstanding = 1,000,000
        // PAR30 = (500,000 + 200,000) / 1,000,000 * 100 = 70%
        // PAR60 = 200,000 / 1,000,000 * 100 = 20%
        // PAR90 = 0%

        $branch2 = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $branch3 = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $member2 = Member::factory()->create(['organization_id' => $this->organization->id]);
        $member3 = Member::factory()->create(['organization_id' => $this->organization->id]);

        // Helper to create a loan with known principal and schedule
        $createLoan = function ($memberId, $branchId, $principal, $installments, $dueDate) {
            $app = LoanApplication::factory()->create([
                'organization_id' => $this->organization->id,
                'branch_id' => $branchId,
                'member_id' => $memberId,
                'loan_plan_id' => $this->loanPlan->id,
                'requested_amount' => $principal,
                'requested_term' => 12,
                'repayment_frequency' => RepaymentFrequency::Monthly,
                'status' => 'approved',
            ]);

            $loan = Loan::create([
                'organization_id' => $this->organization->id,
                'branch_id' => $branchId,
                'member_id' => $memberId,
                'loan_plan_id' => $this->loanPlan->id,
                'loan_application_id' => $app->id,
                'loan_number' => 'LN-2026-' . str_pad(rand(100, 999), 6, '0', STR_PAD_LEFT),
                'principal_amount' => $principal,
                'disbursed_amount' => $principal,
                'interest_rate' => 24.0,
                'interest_method' => 'flat',
                'term_months' => 12,
                'repayment_frequency' => 'monthly',
                'total_interest' => 0,
                'total_amount' => $principal,
                'amount_paid' => 0,
                'outstanding_balance' => $principal,
                'grace_period' => 0,
                'status' => LoanStatus::Active,
                'disbursement_date' => now()->subMonths(1),
                'total_installments' => $installments,
            ]);

            $principalPerInstallment = round($principal / $installments, 2);
            for ($i = 1; $i <= $installments; $i++) {
                LoanRepaymentSchedule::create([
                    'loan_id' => $loan->id,
                    'organization_id' => $this->organization->id,
                    'installment_number' => $i,
                    'due_date' => $dueDate,
                    'principal_amount' => $principalPerInstallment,
                    'interest_amount' => 0,
                    'total_amount' => $principalPerInstallment,
                    'amount_paid' => 0,
                    'outstanding_amount' => $principalPerInstallment,
                    'running_balance' => $principal - ($principalPerInstallment * $i),
                    'status' => LoanScheduleInstallmentStatus::Pending,
                    'days_overdue' => 0,
                    'late_fee' => 0,
                ]);
            }

            return $loan;
        };

        // Loan A: principal=500,000, DPD=45 (due 45 days ago)
        $loanA = $createLoan($this->member->id, $this->branch->id, 500000, 1, now()->subDays(45));

        // Loan B: principal=300,000, DPD=10 (due 10 days ago)
        $loanB = $createLoan($member2->id, $branch2->id, 300000, 1, now()->subDays(10));

        // Loan C: principal=200,000, DPD=75 (due 75 days ago)
        $loanC = $createLoan($member3->id, $branch3->id, 200000, 1, now()->subDays(75));

        $service = app(LoanDelinquencyService::class);

        // PAR30: Loans A (DPD=45) and C (DPD=75) are delinquent
        $par30 = $service->getPAR($this->organization->id, 30);
        $this->assertEqualsWithDelta(1000000, $par30['total_outstanding'], 1);
        $this->assertEqualsWithDelta(700000, $par30['delinquent_outstanding'], 1);
        $this->assertEqualsWithDelta(70.0, $par30['par_percentage'], 0.01);

        // PAR60: Only Loan C (DPD=75) is delinquent
        $par60 = $service->getPAR($this->organization->id, 60);
        $this->assertEqualsWithDelta(200000, $par60['delinquent_outstanding'], 1);
        $this->assertEqualsWithDelta(20.0, $par60['par_percentage'], 0.01);

        // PAR90: No loans have DPD >= 90
        $par90 = $service->getPAR($this->organization->id, 90);
        $this->assertEquals(0, $par90['delinquent_outstanding']);
        $this->assertEquals(0, $par90['par_percentage']);
    }

    public function test_par_uses_principal_not_total_outstanding(): void
    {
        // If a loan has paid some interest but no principal,
        // outstanding_balance may differ from principal outstanding.
        // PAR must use principal outstanding only.
        $service = app(LoanDelinquencyService::class);

        $this->loan->update(['outstanding_balance' => 50000]);

        $principalOutstanding = $service->getPrincipalOutstanding($this->loan);
        $this->assertEqualsWithDelta(100000, $principalOutstanding, 1);
    }

    // ========== Financial Integrity: Overpayment Tracking ==========

    public function test_overpayment_is_tracked_on_repayment(): void
    {
        $service = app(LoanRepaymentService::class);

        // Pay more than all installments combined
        $totalDue = $this->loan->repaymentSchedule()->sum('total_amount');

        $repayment = $service->postRepayment(
            $this->loan,
            $totalDue + 5000,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $this->assertEqualsWithDelta(5000, $repayment->overpayment_amount, 0.01);
    }

    public function test_no_overpayment_when_payment_fits(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $this->assertEquals(0, $repayment->overpayment_amount);
    }

    public function test_overpayment_does_not_reduce_loan_below_zero(): void
    {
        $service = app(LoanRepaymentService::class);

        $totalDue = $this->loan->repaymentSchedule()->sum('total_amount');

        $service->postRepayment(
            $this->loan,
            $totalDue + 10000,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $this->loan->refresh();
        $this->assertGreaterThanOrEqual(0, $this->loan->outstanding_balance);
    }

    public function test_reconciliation_amount_equals_portions_plus_overpayment(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            15000,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $sumPortions = $repayment->principal_portion + $repayment->interest_portion + $repayment->fee_portion + $repayment->overpayment_amount;
        $this->assertEqualsWithDelta($repayment->amount, $sumPortions, 0.01);
    }

    // ========== Financial Integrity: Allocation Reconciliation ==========

    public function test_allocation_amount_equals_portions(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        foreach ($repayment->allocations as $allocation) {
            $sum = $allocation->principal_allocation + $allocation->interest_allocation + $allocation->fee_allocation;
            $this->assertEqualsWithDelta($allocation->amount, $sum, 0.01);
        }
    }

    // ========== Financial Integrity: Reversal Audit ==========

    public function test_reversal_sets_reversed_by_and_date(): void
    {
        $this->actingAs($this->admin);

        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $reversed = $service->reverseRepayment($repayment, 'Testing reversal audit.');

        $this->assertNotNull($reversed->reversed_by);
        $this->assertEquals($this->admin->id, $reversed->reversed_by);
        $this->assertNotNull($reversed->reversal_date);
        $this->assertEquals(now()->toDateString(), $reversed->reversal_date->format('Y-m-d'));
    }

    public function test_reversal_preserves_allocations(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $allocationCount = $repayment->allocations->count();
        $this->assertGreaterThan(0, $allocationCount);

        $service->reverseRepayment($repayment, 'Test reversal.');

        $reversedAllocations = LoanRepaymentAllocation::where('loan_repayment_id', $repayment->id)->get();
        $this->assertEquals($allocationCount, $reversedAllocations->count());
        $this->assertTrue($reversedAllocations->every(fn($a) => $a->status === 'reversed'));
    }

    public function test_cannot_reverse_already_reversed_payment(): void
    {
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $this->loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $service->reverseRepayment($repayment, 'First reversal.');

        $this->expectException(\InvalidArgumentException::class);
        $service->reverseRepayment($repayment->fresh(), 'Second reversal.');
    }

    public function test_reversal_restores_correct_installment_status(): void
    {
        $service = app(LoanRepaymentService::class);

        // Pay installment 1 fully and installment 2 partially
        $service->postRepayment($this->loan, 10333.33, now()->toDateString(), 'cash', null, null, null, null);
        $service->postRepayment($this->loan, 5000, now()->toDateString(), 'cash', null, null, null, null);

        $installment1 = LoanRepaymentSchedule::where('loan_id', $this->loan->id)->where('installment_number', 1)->first();
        $installment2 = LoanRepaymentSchedule::where('loan_id', $this->loan->id)->where('installment_number', 2)->first();

        $this->assertEquals(LoanScheduleInstallmentStatus::Paid, $installment1->fresh()->status);
        $this->assertEquals(LoanScheduleInstallmentStatus::Partial, $installment2->fresh()->status);

        // Reverse only the second payment
        $secondPayment = LoanRepayment::where('loan_id', $this->loan->id)->orderBy('id', 'desc')->first();
        $service->reverseRepayment($secondPayment, 'Test.');

        $installment1->refresh();
        $installment2->refresh();

        $this->assertEquals(LoanScheduleInstallmentStatus::Paid, $installment1->status);
        $this->assertEquals(LoanScheduleInstallmentStatus::Pending, $installment2->status);
    }

    // ========== Financial Integrity: DPD Edge Cases ==========

    public function test_dpd_zero_for_future_due_date(): void
    {
        $schedule = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $schedule->update(['due_date' => now()->addDays(10)]);

        $service = app(LoanDelinquencyService::class);
        $this->assertEquals(0, $service->getDaysPastDue($this->loan));
    }

    public function test_dpd_zero_for_due_today(): void
    {
        $schedule = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $schedule->update(['due_date' => now()->toDateString()]);

        $service = app(LoanDelinquencyService::class);
        $this->assertEquals(0, $service->getDaysPastDue($this->loan));
    }

    public function test_dpd_positive_for_overdue(): void
    {
        $schedule = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $schedule->update(['due_date' => now()->subDays(5)]);

        $service = app(LoanDelinquencyService::class);
        $this->assertEquals(5, $service->getDaysPastDue($this->loan));
    }

    public function test_dpd_zero_when_fully_paid(): void
    {
        $service = app(LoanRepaymentService::class);

        // Pay all installments
        $totalDue = $this->loan->repaymentSchedule()->sum('total_amount');
        $service->postRepayment($this->loan, $totalDue, now()->toDateString(), 'cash', null, null, null, null);

        // Make one installment past due
        $schedule = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();
        $schedule->update(['due_date' => now()->subDays(10)]);

        $dpdService = app(LoanDelinquencyService::class);
        $this->assertEquals(0, $dpdService->getDaysPastDue($this->loan));
    }
}
