<?php

namespace Database\Factories;

use App\Enums\SavingsAccountStatus;
use App\Models\Organization;
use App\Models\SavingsProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class SavingsProductFactory extends Factory
{
    protected $model = SavingsProduct::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement([
                'Ordinary Savings', 'Fixed Deposit', 'Daily Savings',
                'Youth Savings', 'Salary Savings', 'School Fees Savings',
            ]),
            'code' => 'SVP-'.str_pad(self::$counter, 4, '0', STR_PAD_LEFT),
            'description' => fake()->sentence(),
            'minimum_amount' => 10000,
            'maximum_amount' => 5000000,
            'minimum_balance' => 1000,
            'allow_withdrawal' => true,
            'withdrawal_limit' => fake()->optional(0.5)->randomFloat(2, 50000, 500000),
            'status' => SavingsAccountStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => SavingsAccountStatus::Inactive,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => SavingsAccountStatus::Closed,
        ]);
    }
}
