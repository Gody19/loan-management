<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => 'BR-'.fake()->unique()->numerify('####'),
            'name' => fake()->city().' Branch',
            'phone' => '+255'.fake()->numerify('7########'),
            'address' => fake()->address(),
            'manager' => fake()->name(),
            'status' => 'active',
        ];
    }
}
