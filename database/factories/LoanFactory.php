<?php

namespace Database\Factories;

use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Enums\RepaymentFrequency;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        $principal = fake()->randomElement([100000, 250000, 500000, 1000000, 2500000]);
        $interestRate = fake()->randomElement([12.0, 18.0, 24.0, 36.0]);
        $termMonths = fake()->randomElement([3, 6, 12, 24]);
        $totalInterest = round($principal * ($interestRate / 100) * ($termMonths / 12), 2);

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'member_id' => Member::factory(),
            'loan_plan_id' => LoanPlan::factory(),
            'loan_application_id' => LoanApplication::factory(),
            'loan_number' => 'LN-' . date('Y') . '-' . str_pad(self::$counter, 6, '0', STR_PAD_LEFT),
            'principal_amount' => $principal,
            'disbursed_amount' => $principal,
            'interest_rate' => $interestRate,
            'interest_method' => InterestMethod::Flat,
            'term_months' => $termMonths,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'total_interest' => $totalInterest,
            'total_amount' => $principal + $totalInterest,
            'processing_fee' => fake()->randomElement([0, 5000, 10000, 15000]),
            'insurance_fee' => fake()->randomElement([0, 2000, 5000]),
            'amount_paid' => 0,
            'outstanding_balance' => $principal,
            'grace_period' => 0,
            'status' => LoanStatus::PendingDisbursement,
            'total_installments' => $termMonths,
            'installments_paid' => 0,
        ];
    }

    public function pendingDisbursement(): static
    {
        return $this->state(fn () => ['status' => LoanStatus::PendingDisbursement]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => LoanStatus::Active,
            'disbursement_date' => now()->subMonth(),
            'maturity_date' => now()->addMonths(11),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => LoanStatus::Completed,
            'amount_paid' => $this->faker->randomFloat(2, 100000, 1000000),
            'outstanding_balance' => 0,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => LoanStatus::Cancelled]);
    }
}
