<?php

namespace Tests\Feature\Finance;

use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\RepaymentFrequency;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Services\LoanRepaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\HasAccountingSetup;

class LoanLifecycleTest extends TestCase
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

    public function test_repayment_marks_loan_completed_when_balance_reaches_zero(): void
    {
        $service = app(LoanRepaymentService::class);
        $totalDue = (float) $this->loan->repaymentSchedule()->sum('total_amount');

        $service->postRepayment(
            $this->loan,
            round($totalDue + 0.01, 2),
            now()->toDateString(),
            'cash',
            null,
            null,
            'Settle in full',
            null,
        );

        $this->loan->refresh();

        $this->assertEquals(LoanStatus::Completed, $this->loan->status);
        $this->assertEquals(0.0, (float) $this->loan->outstanding_balance);
        $this->assertNull($this->loan->next_payment_date);
        $this->assertEquals(12, $this->loan->installments_paid);
    }

    public function test_completed_loan_accepts_no_further_repayments(): void
    {
        $service = app(LoanRepaymentService::class);
        $totalDue = (float) $this->loan->repaymentSchedule()->sum('total_amount');

        $service->postRepayment(
            $this->loan,
            $totalDue,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $this->assertEquals(LoanStatus::Completed, $this->loan->fresh()->status);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loan must be active to accept repayments.');

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

    public function test_reversing_repayment_reopens_completed_loan(): void
    {
        $service = app(LoanRepaymentService::class);
        $totalDue = (float) $this->loan->repaymentSchedule()->sum('total_amount');

        $repayment = $service->postRepayment(
            $this->loan,
            $totalDue,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $this->assertEquals(LoanStatus::Completed, $this->loan->fresh()->status);

        $firstInstallment = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $reversed = $service->reverseRepayment($repayment, 'Full settlement was an error.');

        $this->assertEquals(LoanRepaymentStatus::Reversed, $reversed->status);

        $this->loan->refresh();

        $this->assertEquals(LoanStatus::Active, $this->loan->status);
        $this->assertEqualsWithDelta(100000, (float) $this->loan->outstanding_balance, 0.01);
        $this->assertEquals(0, $this->loan->amount_paid);
        $this->assertEquals(0, $this->loan->installments_paid);
        $this->assertEquals(
            $firstInstallment->due_date->toDateString(),
            $this->loan->next_payment_date->toDateString()
        );

        $this->assertEquals(
            LoanScheduleInstallmentStatus::Pending,
            $firstInstallment->fresh()->status
        );
    }
}