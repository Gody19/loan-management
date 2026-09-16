<?php

namespace Database\Factories;

use App\Enums\LoanDisbursementStatus;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanDisbursementFactory extends Factory
{
    protected $model = LoanDisbursement::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        $amount = fake()->randomFloat(2, 100000, 5000000);
        $processingFee = fake()->randomFloat(2, 0, 15000);
        $insuranceFee = fake()->randomFloat(2, 0, 5000);

        return [
            'loan_id' => Loan::factory(),
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'disbursement_number' => 'DIS-' . date('Y') . '-' . str_pad(self::$counter, 6, '0', STR_PAD_LEFT),
            'amount' => $amount,
            'processing_fee' => $processingFee,
            'insurance_fee' => $insuranceFee,
            'net_amount' => round($amount - $processingFee - $insuranceFee, 2),
            'disbursement_date' => now()->toDateString(),
            'status' => LoanDisbursementStatus::Pending,
            'disbursement_method' => fake()->randomElement(['cash', 'bank_transfer', 'mobile_money']),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => LoanDisbursementStatus::Confirmed]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => LoanDisbursementStatus::Rejected,
            'rejection_reason' => 'Insufficient documentation.',
        ]);
    }
}
