<?php

namespace Database\Factories;

use App\Enums\AiLearningExampleStatus;
use App\Models\AiEvaluation;
use App\Models\AiLearningExample;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiLearningExample>
 */
class AiLearningExampleFactory extends Factory
{
    protected $model = AiLearningExample::class;

    public function definition(): array
    {
        return [
            'ai_evaluation_id' => AiEvaluation::factory(),
            'ai_feedback_id' => null,
            'organization_id' => null,
            'branch_id' => null,
            'dataset_version' => 1,
            'status' => AiLearningExampleStatus::Active,
            'input_text' => 'How do I check my loan balance?',
            'original_response' => 'You can ask the assistant for your loan balance.',
            'corrected_response' => null,
            'evaluation_metadata' => [],
            'sanitization_report' => [],
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ];
    }

    public function superseded(): static
    {
        return $this->state(fn () => ['status' => AiLearningExampleStatus::Superseded]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['status' => AiLearningExampleStatus::Revoked]);
    }
}
