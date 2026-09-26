<?php

namespace Database\Factories;

use App\Enums\AiConversationStatus;
use App\Enums\AiConversationType;
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
            'type' => AiConversationType::Private,
            'uuid' => null,
            'provider' => null,
            'model' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => AiConversationStatus::Archived]);
    }

    public function public(): static
    {
        return $this->state(fn () => [
            'type' => AiConversationType::Public,
            'user_id' => null,
            'organization_id' => null,
            'branch_id' => null,
            'vicoba_group_id' => null,
            'uuid' => null,
        ]);
    }
}
