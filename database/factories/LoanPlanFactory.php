<?php

namespace Database\Factories;

use App\Enums\InterestMethod;
use App\Enums\LoanPlanStatus;
use App\Enums\LoanPurpose;
use App\Enums\RepaymentFrequency;
use App\Models\LoanPlan;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanPlanFactory extends Factory
{
    protected $model = LoanPlan::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement([
                'Ordinary Business Loan',
                'Emergency Loan',
                'Education Loan',
                'Agricultural Loan',
                'Development Loan',
                'Personal Loan',
            ]),
            'code' => 'LPN-'.str_pad(self::$counter, 4, '0', STR_PAD_LEFT),
            'description' => fake()->sentence(),
            'loan_purpose' => LoanPurpose::Development,
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'interest_rate' => 1.5,
            'interest_method' => InterestMethod::Flat,
            'minimum_term' => 3,
            'maximum_term' => 24,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'maximum_active_loans' => 1,
            'requires_guarantor' => false,
            'minimum_guarantors' => 0,
            'requires_collateral' => false,
            'minimum_savings_balance' => 100000,
            'savings_multiplier' => 3,
            'share_multiplier' => 2,
            'maximum_loan_to_savings_ratio' => 5,
            'grace_period' => 0,
            'processing_fee' => 0,
            'insurance_fee' => 0,
            'late_payment_allowed' => true,
            'status' => LoanPlanStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => LoanPlanStatus::Inactive]);
    }

    public function emergency(): static
    {
        return $this->state(fn () => [
            'name' => 'Emergency Loan',
            'code' => 'LPN-EMG-'.str_pad(self::$counter, 4, '0', STR_PAD_LEFT),
            'loan_purpose' => LoanPurpose::Emergency,
            'minimum_amount' => 10000,
            'maximum_amount' => 500000,
            'requires_guarantor' => false,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 1,
        ]);
    }

    public function business(): static
    {
        return $this->state(fn () => [
            'name' => 'Business Loan',
            'code' => 'LPN-BUS-'.str_pad(self::$counter, 4, '0', STR_PAD_LEFT),
            'loan_purpose' => LoanPurpose::Business,
            'minimum_amount' => 100000,
            'maximum_amount' => 10000000,
            'requires_guarantor' => true,
            'minimum_guarantors' => 2,
            'requires_collateral' => true,
            'minimum_savings_balance' => 500000,
            'savings_multiplier' => 4,
        ]);
    }
}
