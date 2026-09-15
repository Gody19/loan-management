<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class WelfareTransactionFactory extends Factory
{
    protected $model = WelfareTransaction::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        $amount = 50000;
        $balanceBefore = 0;

        return [
            'welfare_account_id' => WelfareAccount::factory(),
            'member_id' => Member::factory(),
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'vicoba_group_id' => VicobaGroup::factory(),
            'transaction_number' => 'WFT-'.str_pad(self::$counter, 6, '0', STR_PAD_LEFT),
            'transaction_type' => 'contribution',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore + $amount,
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'payment_method_id' => PaymentMethod::factory(),
            'reference' => fake()->optional(0.5)->bothify('REF-####-####'),
            'description' => fake()->optional(0.3)->sentence(),
            'status' => 'completed',
            'created_by' => User::factory(),
        ];
    }

    public function benefit(): static
    {
        return $this->state(function () {
            $amount = fake()->randomFloat(2, 10000, 200000);
            $balanceBefore = $amount + 50000;

            return [
                'transaction_type' => 'benefit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore - $amount,
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
