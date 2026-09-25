<?php

namespace Database\Factories;

use App\Enums\AiEvaluationStatus;
use App\Models\AiEvaluation;
use App\Models\AiFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiEvaluation>
 */
class AiEvaluationFactory extends Factory
{
    protected $model = AiEvaluation::class;

    public function definition(): array
    {
        return [
            'ai_feedback_id' => AiFeedback::factory(),
            'ai_message_id' => null,
            'ai_conversation_id' => null,
            'evaluator_id' => User::factory(),
            'organization_id' => null,
            'branch_id' => null,
            'status' => AiEvaluationStatus::Pending,
            'scores' => [],
            'notes' => null,
            'rejection_reason' => null,
            'reviewed_at' => null,
        ];
    }

    public function inReview(): static
    {
        return $this->state(fn () => ['status' => AiEvaluationStatus::InReview]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => AiEvaluationStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => AiEvaluationStatus::Rejected,
            'rejection_reason' => 'Not a useful learning example.',
            'reviewed_at' => now(),
        ]);
    }
}
