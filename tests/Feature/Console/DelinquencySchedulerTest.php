<?php

namespace Tests\Feature\Console;

use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanRepaymentSchedule;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelinquencySchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_marks_overdue_installments_for_all_organizations(): void
    {
        $org = Organization::factory()->create(['status' => 'active']);

        $loan = Loan::factory()->active()->create([
            'organization_id' => $org->id,
            'status' => LoanStatus::Active,
        ]);

        $overdueInstallment = LoanRepaymentSchedule::factory()->create([
            'loan_id' => $loan->id,
            'organization_id' => $org->id,
            'installment_number' => 1,
            'due_date' => now()->subDays(5),
            'status' => LoanScheduleInstallmentStatus::Pending,
        ]);

        $futureInstallment = LoanRepaymentSchedule::factory()->create([
            'loan_id' => $loan->id,
            'organization_id' => $org->id,
            'installment_number' => 2,
            'due_date' => now()->addDays(5),
            'status' => LoanScheduleInstallmentStatus::Pending,
        ]);

        $this->artisan('loans:update-delinquency')
            ->assertSuccessful()
            ->expectsOutputToContain('Marked 1 loan installment(s) as overdue.');

        $this->assertDatabaseHas('loan_repayment_schedules', [
            'id' => $overdueInstallment->id,
            'status' => LoanScheduleInstallmentStatus::Overdue->value,
            'days_overdue' => 5,
        ]);

        $this->assertDatabaseHas('loan_repayment_schedules', [
            'id' => $futureInstallment->id,
            'status' => LoanScheduleInstallmentStatus::Pending->value,
        ]);
    }

    public function test_delinquency_command_is_registered_in_schedule(): void
    {
        $this->artisan('schedule:list')
            ->assertSuccessful()
            ->expectsOutputToContain('loans:update-delinquency');
    }
}