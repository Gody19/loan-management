<?php

namespace Database\Factories;

use App\Enums\LoanScheduleInstallmentStatus;
use App\Models\Loan;
use App\Models\LoanRepaymentSchedule;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanRepaymentScheduleFactory extends Factory
{
    protected $model = LoanRepaymentSchedule::class;

    public function definition(): array
    {
        $totalAmount = fake()->randomFloat(2, 50000, 200000);

        return [
            'loan_id' => Loan::factory(),
            'organization_id' => Organization::factory(),
            'installment_number' => fake()->numberBetween(1, 24),
            'due_date' => fake()->dateTimeBetween('+1 month', '+24 months'),
            'principal_amount' => fake()->randomFloat(2, 20000, 100000),
            'interest_amount' => fake()->randomFloat(2, 5000, 50000),
            'total_amount' => $totalAmount,
            'amount_paid' => 0,
            'outstanding_amount' => $totalAmount,
            'running_balance' => fake()->randomFloat(2, 0, 500000),
            'status' => LoanScheduleInstallmentStatus::Pending,
            'days_overdue' => 0,
            'late_fee' => 0,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => LoanScheduleInstallmentStatus::Paid,
            'amount_paid' => $this->faker->randomFloat(2, 50000, 200000),
            'outstanding_amount' => 0,
            'paid_date' => now()->subDays(rand(1, 30)),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => LoanScheduleInstallmentStatus::Overdue,
            'days_overdue' => rand(1, 30),
        ]);
    }
}
