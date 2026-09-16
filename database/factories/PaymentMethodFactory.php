<?php

namespace Database\Factories;

use App\Enums\PaymentMethodType;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement([
                'Cash', 'Bank Transfer', 'Mobile Money',
                'Visa Card', 'MasterCard', 'Cheque',
            ]),
            'code' => 'PMT-'.str_pad(self::$counter, 4, '0', STR_PAD_LEFT),
            'type' => fake()->randomElement(PaymentMethodType::cases()),
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 'inactive',
        ]);
    }
}
