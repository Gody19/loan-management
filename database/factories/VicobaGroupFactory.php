<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\VicobaGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class VicobaGroupFactory extends Factory
{
    protected $model = VicobaGroup::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code' => 'GRP-'.fake()->unique()->numerify('####'),
            'name' => fake()->randomElement([
                'Jeshi', 'Umoja', 'Maendeleo', 'Ujamaa', 'Amani',
                'Tumaini', 'Furaha', 'Baraka', 'Imani', 'Upendo',
            ]).' '.fake()->randomElement(['A', 'B', 'C', 'I', 'II', 'III']),
            'meeting_day' => fake()->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']),
            'meeting_time' => fake()->randomElement(['09:00', '10:00', '14:00', '15:00']),
            'meeting_location' => fake()->optional(0.7)->city(),
            'description' => fake()->optional(0.5)->sentence(),
            'status' => 'active',
        ];
    }
}
