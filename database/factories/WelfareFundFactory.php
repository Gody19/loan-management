<?php

namespace Database\Factories;

use App\Enums\SavingsAccountStatus;
use App\Models\Organization;
use App\Models\WelfareFund;
use Illuminate\Database\Eloquent\Factories\Factory;

class WelfareFundFactory extends Factory
{
    protected $model = WelfareFund::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement([
                'Welfare Fund', 'Emergency Fund', 'Education Fund',
                'Health Fund', 'Benevolent Fund', 'Disaster Relief Fund',
            ]),
            'code' => 'WLF-'.str_pad(self::$counter, 4, '0', STR_PAD_LEFT),
            'description' => fake()->sentence(),
            'contribution_type' => 'fixed',
            'default_amount' => 50000,
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
