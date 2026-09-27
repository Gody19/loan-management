<?php

namespace Database\Factories;

use App\Enums\PredictionConfidence;
use App\Enums\PredictiveDataQuality;
use App\Enums\PredictiveInsightStatus;
use App\Enums\PredictiveInsightType;
use App\Models\AiPrediction;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiPredictionFactory extends Factory
{
    protected $model = AiPrediction::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => PredictiveInsightType::PortfolioForecast,
            'status' => PredictiveInsightStatus::Generated,
            'scope' => 'organization',
            'method' => 'linear_trend',
            'model_version' => 'statistical-baseline-v1',
            'target_period' => now()->startOfMonth()->addMonth()->format('Y-m'),
            'data_through' => now()->toDateString(),
            'horizon' => 3,
            'confidence' => PredictionConfidence::Medium,
            'data_quality' => PredictiveDataQuality::Good,
            'value_total' => 1000000,
            'currency' => 'TZS',
            'series' => [],
            'factors' => [],
            'assumptions' => ['method' => 'linear_trend'],
            'explanation' => 'Test prediction.',
            'generated_at' => now(),
        ];
    }
}
