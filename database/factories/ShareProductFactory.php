<?php

namespace Database\Factories;

use App\Enums\SavingsAccountStatus;
use App\Models\Organization;
use App\Models\ShareProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShareProductFactory extends Factory
{
    protected $model = ShareProduct::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement([
                'Ordinary Shares', 'Premium Shares', 'Youth Shares',
                'Institutional Shares', 'Class A Shares',
            ]),
            'code' => 'SHP-'.str_pad(self::$counter, 4, '0', STR_PAD_LEFT),
            'description' => fake()->sentence(),
            'share_price' => 10000,
            'minimum_shares' => 1,
            'maximum_shares' => 10000,
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
