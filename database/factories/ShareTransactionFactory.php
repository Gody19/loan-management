<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\ShareAccount;
use App\Models\ShareTransaction;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShareTransactionFactory extends Factory
{
    protected $model = ShareTransaction::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        $quantity = 100;
        $sharePrice = 10000;

        return [
            'share_account_id' => ShareAccount::factory(),
            'member_id' => Member::factory(),
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'vicoba_group_id' => VicobaGroup::factory(),
            'transaction_number' => 'SHT-'.str_pad(self::$counter, 6, '0', STR_PAD_LEFT),
            'transaction_type' => 'purchase',
            'quantity' => $quantity,
            'share_price' => $sharePrice,
            'amount' => $quantity * $sharePrice,
            'balance_shares_before' => 0,
            'balance_shares_after' => $quantity,
            'balance_value_before' => 0,
            'balance_value_after' => $quantity * $sharePrice,
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'payment_method_id' => PaymentMethod::factory(),
            'reference' => fake()->optional(0.5)->bothify('REF-####-####'),
            'description' => fake()->optional(0.3)->sentence(),
            'status' => 'completed',
            'created_by' => User::factory(),
        ];
    }

    public function redeem(): static
    {
        return $this->state(function () {
            $quantity = fake()->numberBetween(1, 50);
            $sharePrice = 10000;

            return [
                'transaction_type' => 'redeem',
                'quantity' => $quantity,
                'share_price' => $sharePrice,
                'amount' => $quantity * $sharePrice,
                'balance_shares_before' => 100,
                'balance_shares_after' => 100 - $quantity,
                'balance_value_before' => 100 * $sharePrice,
                'balance_value_after' => (100 - $quantity) * $sharePrice,
            ];
        });
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
        ]);
    }
}
