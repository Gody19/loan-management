<?php

namespace Database\Factories;

use App\Enums\LoanRepaymentStatus;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanRepaymentFactory extends Factory
{
    protected $model = LoanRepayment::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 100000);

        return [
            'loan_id' => Loan::factory(),
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'member_id' => Member::factory(),
            'payment_method_id' => null,
            'received_by' => User::factory(),
            'repayment_number' => 'RPT-' . date('Y') . '-' . str_pad(fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'amount' => $amount,
            'principal_portion' => $amount * 0.7,
            'interest_portion' => $amount * 0.25,
            'fee_portion' => $amount * 0.05,
            'payment_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'payment_method' => fake()->randomElement(['cash', 'mobile_money', 'bank_transfer', 'check']),
            'reference_number' => fake()->optional()->numerify('REF-#######'),
            'status' => LoanRepaymentStatus::Posted,
            'idempotency_key' => fake()->uuid,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanRepaymentStatus::Posted,
        ]);
    }

    public function reversed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanRepaymentStatus::Reversed,
            'reversal_reason' => 'Payment reversed due to error.',
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'cash',
        ]);
    }

    public function mobileMoney(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'mobile_money',
        ]);
    }
}
