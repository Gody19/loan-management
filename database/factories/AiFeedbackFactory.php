<?php

namespace Database\Factories;

use App\Enums\AiFeedbackStatus;
use App\Enums\AiFeedbackType;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiFeedback>
 */
class AiFeedbackFactory extends Factory
{
    protected $model = AiFeedback::class;

    public function definition(): array
    {
        return [
            'ai_message_id' => AiMessage::factory(),
            'ai_conversation_id' => null,
            'user_id' => User::factory(),
            'organization_id' => null,
            'branch_id' => null,
            'ai_model_version_id' => null,
            'type' => AiFeedbackType::Positive,
            'status' => AiFeedbackStatus::Submitted,
            'correction' => null,
            'reason' => null,
            'provider' => 'fake',
            'model' => 'gpt-test',
        ];
    }

    public function positive(): static
    {
        return $this->state(fn () => ['type' => AiFeedbackType::Positive, 'correction' => null]);
    }

    public function negative(): static
    {
        return $this->state(fn () => ['type' => AiFeedbackType::Negative, 'correction' => null]);
    }

    public function correction(string $text = 'The outstanding amount is stated incorrectly.'): static
    {
        return $this->state(fn () => ['type' => AiFeedbackType::Correction, 'correction' => $text]);
    }

    public function reviewed(): static
    {
        return $this->state(fn () => ['status' => AiFeedbackStatus::Reviewed]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn () => ['status' => AiFeedbackStatus::Withdrawn]);
    }
}
