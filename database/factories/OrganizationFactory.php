<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'registration_number' => 'ORG-'.fake()->unique()->numerify('######'),
            'phone' => '+255'.fake()->numerify('7########'),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'region' => fake()->randomElement([
                'Dar es Salaam', 'Arusha', 'Mwanza', 'Dodoma', 'Mbeya',
            ]),
            'district' => fake()->city(),
            'status' => 'active',
        ];
    }
}
