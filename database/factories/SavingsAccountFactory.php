<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class SavingsAccountFactory extends Factory
{
    protected $model = SavingsAccount::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        return [
            'member_id' => Member::factory(),
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'vicoba_group_id' => VicobaGroup::factory(),
            'savings_product_id' => SavingsProduct::factory(),
            'account_number' => 'SAV-'.str_pad(self::$counter, 6, '0', STR_PAD_LEFT),
            'opening_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'status' => 'active',
            'current_balance' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 'inactive',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
        ]);
    }
}
