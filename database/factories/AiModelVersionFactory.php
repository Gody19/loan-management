<?php

namespace Database\Factories;

use App\Enums\AiModelVersionStatus;
use App\Models\AiModelVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiModelVersionFactory extends Factory
{
    protected $model = AiModelVersion::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        return [
            'provider' => 'openai',
            'model' => 'gpt-test-' . self::$counter,
            'display_name' => 'OpenAI GPT Test ' . self::$counter,
            'version' => 'test',
            'status' => AiModelVersionStatus::Active,
            'configuration' => ['temperature' => 0.7, 'max_tokens' => 1024],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => AiModelVersionStatus::Inactive]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => AiModelVersionStatus::Archived]);
    }
}