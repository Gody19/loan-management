<?php

namespace Database\Factories;

use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiMessageFactory extends Factory
{
    protected $model = AiMessage::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        return [
            'ai_conversation_id' => AiConversation::factory(),
            'role' => fake()->randomElement(AiMessageRole::values()),
            'content' => fake()->sentence(),
            'provider_message_id' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'total_tokens' => null,
            'model' => null,
            'metadata' => [],
        ];
    }

    public function user(): static
    {
        return $this->state(fn () => ['role' => AiMessageRole::User]);
    }

    public function assistant(): static
    {
        return $this->state(fn () => ['role' => AiMessageRole::Assistant]);
    }

    public function system(): static
    {
        return $this->state(fn () => ['role' => AiMessageRole::System]);
    }
}