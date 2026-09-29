<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\PredictiveIntelligence\Services\PredictiveIntelligenceService;
use App\Enums\PredictiveInsightStatus;
use App\Enums\PredictiveInsightType;
use App\Models\AiPrediction;
use App\Models\User;

/**
 * ai.predictive.view — statistical predictive intelligence for the acting
 * user's own organizations/branches. No arguments are accepted: tenant scope
 * is derived exclusively from the trusted context, results are refreshed
 * lazily (idempotent per data snapshot), and the payload is strictly
 * aggregate — never member-level detail.
 */
class PredictiveInsightTool implements AiToolInterface
{
    public function __construct(
        private readonly PredictiveIntelligenceService $predictive,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $insights = [];

        foreach (PredictiveInsightType::cases() as $type) {
            foreach ($this->predictive->refresh($type, $context->organizationIds, $context->branchIds, $user) as $prediction) {
                $insights[] = $this->serialize($prediction);
            }
        }

        return [
            'as_of' => now()->toISOString(),
            'insights' => $insights,
            'note' => 'Statistical indications computed from historical FinancePro records; not guarantees and not used in any decision about a member.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(AiPrediction $prediction): array
    {
        $series = [];

        foreach ($prediction->series ?? [] as $row) {
            $series[] = [
                'period' => $row['period'],
                'value' => (float) $this->seriesValue($prediction->type, $row),
                'is_forecast' => (bool) ($row['is_forecast'] ?? false),
            ];
        }

        return [
            'type' => $prediction->type->value,
            'organization_id' => $prediction->organization_id,
            'status' => $prediction->status->value,
            'method' => $prediction->method,
            'confidence' => $prediction->confidence->value,
            'data_quality' => $prediction->data_quality->value,
            'data_window' => [
                'from' => $prediction->data_from?->toDateString(),
                'through' => $prediction->data_through->toDateString(),
            ],
            'generated_at' => $prediction->generated_at?->toISOString(),
            'fresh' => $prediction->status !== PredictiveInsightStatus::Stale,
            'horizon' => $prediction->horizon,
            'value_total' => $prediction->value_total === null ? null : (float) $prediction->value_total,
            'currency' => $prediction->currency,
            'risk_level' => $prediction->type === PredictiveInsightType::DelinquencyRisk && $prediction->value_total !== null
                ? $this->riskLevel((float) $prediction->value_total)
                : null,
            'series' => $series,
            'factors' => $prediction->factors ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function seriesValue(PredictiveInsightType $type, array $row): mixed
    {
        return match ($type) {
            PredictiveInsightType::PortfolioForecast => $row['outstanding_end'] ?? 0,
            PredictiveInsightType::CashflowForecast => $row['net'] ?? 0,
            PredictiveInsightType::CollectionForecast => $row['collected'] ?? 0,
            PredictiveInsightType::DelinquencyRisk => 0,
        };
    }

    protected function riskLevel(float $score): string
    {
        $medium = (float) config('predictive-intelligence.delinquency_risk_levels.medium_threshold', 34);
        $high = (float) config('predictive-intelligence.delinquency_risk_levels.high_threshold', 67);

        if ($score >= $high) {
            return 'high';
        }

        if ($score >= $medium) {
            return 'medium';
        }

        return 'low';
    }
}
