<?php

namespace Database\Factories;

use App\Models\LoanRepaymentAllocation;
use App\Models\LoanRepayment;
use App\Models\Loan;
use App\Models\LoanRepaymentSchedule;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanRepaymentAllocationFactory extends Factory
{
    protected $model = LoanRepaymentAllocation::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 10000);

        return [
            'loan_repayment_id' => LoanRepayment::factory(),
            'loan_id' => Loan::factory(),
            'loan_repayment_schedule_id' => LoanRepaymentSchedule::factory(),
            'organization_id' => Organization::factory(),
            'amount' => $amount,
            'principal_allocation' => $amount * 0.7,
            'interest_allocation' => $amount * 0.25,
            'fee_allocation' => $amount * 0.05,
        ];
    }
}
