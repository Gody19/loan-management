<?php

namespace Database\Factories;

use App\Enums\AiConversationStatus;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiConversationFactory extends Factory
{
    protected $model = AiConversation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => null,
            'branch_id' => null,
            'vicoba_group_id' => null,
            'title' => fake()->optional(0.6)->words(4, true),
            'status' => AiConversationStatus::Active,
            'provider' => null,
            'model' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => AiConversationStatus::Archived]);
    }
}