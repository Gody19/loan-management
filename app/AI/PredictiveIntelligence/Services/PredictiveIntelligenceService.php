<?php

namespace App\AI\PredictiveIntelligence\Services;

use App\AI\DTOs\AiContextData;
use App\AI\PredictiveIntelligence\DataQualityService;
use App\Enums\LoanRepaymentStatus;
use App\Enums\PredictiveInsightStatus;
use App\Enums\PredictiveInsightType;
use App\Models\AiPrediction;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Predictive Intelligence orchestrator (Phase 11.8).
 *
 * Routes every insight domain to its deterministic statistical service and
 * persists the outcome as an idempotent, audited snapshot per organization:
 * re-running the same (type, method, data snapshot) updates the same row, a
 * newer snapshot supersedes older current ones, aged rows are marked stale,
 * and every generation and read is recorded in the audit trail. The whole
 * layer is advisory — the persisted snapshots never feed a business rule.
 */
class PredictiveIntelligenceService
{
    public function __construct(
        private readonly DataQualityService $quality,
        private readonly PortfolioForecastService $portfolio,
        private readonly DelinquencyRiskService $delinquency,
        private readonly CashflowForecastService $cashflow,
        private readonly CollectionForecastService $collection,
        private readonly AuditService $audit,
    ) {}

    /**
     * Generate (or refresh if the snapshot is old) every insight domain for
     * the given organizations and branches. Returns one current AiPrediction
     * per organization per domain, ordered by organization.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return Collection<int, AiPrediction>
     */
    public function refresh(PredictiveInsightType $type, array $organizationIds, array $branchIds = [], ?User $caller = null)
    {
        $predictions = collect();

        foreach ($organizationIds as $organizationId) {
            $latest = $this->latest($type, $organizationId);
            $newestSource = $this->newestSourceDate($organizationId, $branchIds);
            $aged = false;

            if ($latest !== null) {
                $staleAfter = max(0, (int) config('predictive-intelligence.stale_after_days', 7));
                $aged = $latest->data_through->lt(now()->startOfDay()->subDays($staleAfter));

                if ($aged && $latest->status->isCurrent()) {
                    $latest->update(['status' => PredictiveInsightStatus::Stale->value]);
                }

                $last = $latest->data_through->toDateString();

                // Snapshot already reflects the newest observable activity and
                // has not aged — reuse the stored snapshot.
                if (! $aged && ($newestSource === null || $newestSource <= $last)) {
                    $predictions->push($latest);

                    continue;
                }
            }

            $predictions->push($this->generate($type, $organizationId, $branchIds, $caller));
        }

        return $predictions;
    }

    /**
     * Run one deterministic insight and persist it idempotently. The unique
     * key is (organization, type, method, data snapshot), so calling generation
     * twice with the same snapshot never duplicates the record.
     *
     * @param  int[]  $branchIds
     */
    public function generate(PredictiveInsightType $type, int $organizationId, array $branchIds = [], ?User $caller = null): AiPrediction
    {
        $horizon = max(1, min(
            (int) config('predictive-intelligence.max_horizon', 6),
            (int) config('predictive-intelligence.default_horizon', 3),
        ));

        $outcome = match ($type) {
            PredictiveInsightType::PortfolioForecast => $this->portfolio->predict($organizationId, $branchIds, $horizon),
            PredictiveInsightType::DelinquencyRisk => $this->delinquency->predict($organizationId, $branchIds, $horizon),
            PredictiveInsightType::CashflowForecast => $this->cashflow->predict($organizationId, $branchIds, $horizon),
            PredictiveInsightType::CollectionForecast => $this->collection->predict($organizationId, $branchIds, $horizon),
        };

        $prediction = AiPrediction::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'type' => $type->value,
                'method' => $outcome['method'],
                'data_through' => $outcome['data_through'],
            ],
            [
                'status' => $outcome['status'],
                'scope' => 'organization',
                'model_version' => (string) config('predictive-intelligence.model_version', 'statistical-baseline-v1'),
                'target_period' => $outcome['target_period'],
                'horizon' => $outcome['horizon'],
                'confidence' => $outcome['confidence'],
                'data_quality' => $outcome['data_quality'],
                'value_total' => $outcome['value_total'],
                'currency' => $outcome['currency'],
                'series' => $outcome['series'],
                'factors' => $outcome['factors'],
                'assumptions' => $outcome['assumptions'],
                'explanation' => $outcome['explanation'],
                'generated_by' => $caller?->id,
                'generated_at' => now(),
            ],
        );

        // A fresh snapshot supersedes every other current row of the same
        // domain so exactly one snapshot is current per domain at a time.
        AiPrediction::where('organization_id', $organizationId)
            ->where('type', $type->value)
            ->whereKeyNot($prediction->id)
            ->whereIn('status', [PredictiveInsightStatus::Generated->value, PredictiveInsightStatus::Stale->value])
            ->update(['status' => PredictiveInsightStatus::Superseded->value]);

        $this->audit->log('ai.predictive.generated', $prediction, [], [
            'type' => $type->value,
            'status' => $outcome['status'],
            'method' => $outcome['method'],
            'data_quality' => $outcome['data_quality'],
            'organization_id' => $organizationId,
        ]);

        return $prediction;
    }

    /**
     * Latest current (generated or stale) snapshot for a domain + organization.
     */
    public function latest(PredictiveInsightType $type, int $organizationId): ?AiPrediction
    {
        return AiPrediction::query()
            ->forOrganization($organizationId)
            ->ofType($type)
            ->whereIn('status', [PredictiveInsightStatus::Generated->value, PredictiveInsightStatus::Stale->value])
            ->orderByDesc('generated_at')
            ->first();
    }

    /**
     * Newest observable (past) activity for an organization scope: the most
     * recent posted repayment or disbursement date, or null when none exists.
     * Future-dated schedules are never considered "new data".
     *
     * @param  int[]  $branchIds
     */
    public function newestSourceDate(int $organizationId, array $branchIds = []): ?string
    {
        $repayment = LoanRepayment::where('organization_id', $organizationId)
            ->where('status', LoanRepaymentStatus::Posted)
            ->where('payment_date', '<=', now())
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->max('payment_date');

        $disbursement = Loan::where('organization_id', $organizationId)
            ->whereNotNull('disbursement_date')
            ->where('disbursement_date', '<=', now())
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->max('disbursement_date');

        $latest = null;

        foreach ([$repayment, $disbursement] as $candidate) {
            if ($candidate !== null && ($latest === null || $candidate > $latest)) {
                $latest = $candidate;
            }
        }

        if ($latest === null) {
            return null;
        }

        if (is_string($latest)) {
            return substr($latest, 0, 10);
        }

        return method_exists($latest, 'toDateString') ? $latest->toDateString() : (string) $latest;
    }

    /**
     * Per-organization dashboard payload for the trusted context: every
     * domain, one snapshot per organization, refreshed lazily.
     *
     * @return array<string, array{label: string, rows: array<int, array{organization_id: int, organization_name: string, prediction: ?AiPrediction}>}>
     */
    public function forDashboard(AiContextData $context): array
    {
        $payload = [];

        foreach (PredictiveInsightType::cases() as $type) {
            $refreshed = $this->refresh($type, $context->organizationIds, $context->branchIds);

            $rows = [];

            foreach ($context->organizationIds as $organizationId) {
                $prediction = $refreshed->first(
                    fn (AiPrediction $prediction) => $prediction->organization_id === $organizationId,
                ) ?? $this->latest($type, $organizationId);

                $rows[] = [
                    'organization_id' => $organizationId,
                    'organization_name' => $this->organizationName($organizationId),
                    'prediction' => $prediction,
                ];
            }

            $payload[$type->value] = [
                'label' => $type->label(),
                'rows' => $rows,
            ];
        }

        return $payload;
    }

    protected function organizationName(int $organizationId): string
    {
        return (string) DB::table('organizations')->where('id', $organizationId)->value('name');
    }
}
