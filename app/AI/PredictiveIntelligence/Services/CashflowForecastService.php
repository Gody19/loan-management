<?php

namespace App\AI\PredictiveIntelligence\Services;

use App\AI\PredictiveIntelligence\DataQualityService;
use App\AI\PredictiveIntelligence\StatisticalForecastService;
use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanStatus;
use App\Enums\PredictiveDataQuality;
use App\Models\Loan;
use App\Models\LoanRepayment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Cash-flow forecast (Phase 11.8, §31).
 *
 * Monthly inflows (posted loan repayments) and outflows (disbursements) over
 * complete months feed independent statistical baselines; the forecast net is
 * derived from the two projected streams. Sources are the same authoritative
 * operational loan/repayment tables used by the Phase 11.7 financial
 * intelligence services — cash-flow here is the loan cash cycle, advisory
 * and never a ledger posting.
 */
class CashflowForecastService
{
    public function __construct(
        private readonly DataQualityService $quality,
        private readonly StatisticalForecastService $statistics,
    ) {}

    /**
     * @param  int[]  $branchIds
     * @return array<string, mixed>
     */
    public function predict(int $organizationId, array $branchIds = [], int $horizon = 3): array
    {
        $today = CarbonImmutable::today();
        $currency = (string) config('predictive-intelligence.currency', 'TZS');
        $months = max(1, (int) config('predictive-intelligence.history_months', 12));

        $series = [];
        $activityMonths = 0;

        for ($offset = $months; $offset >= 1; $offset--) {
            $month = $today->startOfMonth()->subMonths($offset);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $inflows = $this->inflows($organizationId, $branchIds, $start, $end);
            $outflows = $this->outflows($organizationId, $branchIds, $start, $end);

            if ($inflows > 0 || $outflows > 0) {
                $activityMonths++;
            }

            $series[] = [
                'period' => $month->format('Y-m'),
                'inflows' => $inflows,
                'outflows' => $outflows,
                'net' => round($inflows - $outflows, 2),
                'is_forecast' => false,
            ];
        }

        $observationCount = $activityMonths;
        $grade = $this->quality->grade($observationCount);

        if ($grade === PredictiveDataQuality::Insufficient) {
            return $this->insufficient($organizationId, $currency, $observationCount, $today);
        }

        $expanded = $this->quality->trimLeadingInactive($series, ['inflows', 'outflows']);
        $inflowValues = array_map(fn (array $row) => (float) $row['inflows'], $expanded !== [] ? $expanded : $series);
        $outflowValues = array_map(fn (array $row) => (float) $row['outflows'], $expanded !== [] ? $expanded : $series);

        $inflowMethod = $this->statistics->methodFor($observationCount, $inflowValues);
        $outflowMethod = $this->statistics->methodFor($observationCount, $outflowValues);

        $method = in_array($inflowMethod['method'], ['linear_trend', 'moving_average'], true)
            ? $inflowMethod['method']
            : $outflowMethod['method'];
        $confidence = $inflowMethod['confidence'] === 'high' && $outflowMethod['confidence'] === 'high'
            ? 'high'
            : ($inflowMethod['confidence'] === 'low' || $outflowMethod['confidence'] === 'low' ? 'low' : 'medium');

        $forecastInflows = $this->statistics->forecastNext($horizon, $inflowValues, $method);
        $forecastOutflows = $this->statistics->forecastNext($horizon, $outflowValues, $method);

        $forecast = [];

        foreach (array_values($forecastInflows) as $index => $inflow) {
            $outflow = $forecastOutflows[$index];
            $net = round($inflow - $outflow, 2);

            $forecast[] = [
                'period' => $today->addMonths($index + 1)->format('Y-m'),
                'inflows' => round($inflow, 2),
                'outflows' => round($outflow, 2),
                'net' => $net,
                'is_forecast' => true,
            ];
        }

        $valueTotal = round(array_sum(array_column($forecast, 'net')), 2);

        return [
            'status' => 'generated',
            'method' => $method,
            'target_period' => $forecast[0]['period'],
            'data_through' => $today->toDateString(),
            'data_from' => $this->quality->dataFrom($today, $months),
            'horizon' => $horizon,
            'confidence' => $confidence,
            'data_quality' => $grade->value,
            'observation_count' => $observationCount,
            'value_total' => $valueTotal,
            'currency' => $currency,
            'series' => array_merge($series, $forecast),
            'factors' => [
                "Cumulative projected net loan cash flow over the next {$horizon} month(s): ".number_format($valueTotal, 2)." {$currency}.",
                'Projected inflows next month: '.number_format((float) $forecast[0]['inflows'], 2)." {$currency}; projected outflows: ".number_format((float) $forecast[0]['outflows'], 2)." {$currency}.",
            ],
            'assumptions' => [
                'method' => $method,
                'confidence' => $confidence,
                'observation_count' => $observationCount,
                'forecast_horizon' => $horizon,
                'incomplete_period_excluded' => $this->quality->incompletePeriodKey(),
                'scope_note' => 'Cash flow reflects the loan cycle (posted repayments against disbursements), advisory only.',
            ],
            'explanation' => "Cash-flow forecast extrapolated from {$observationCount} complete month(s) of posted repayment and disbursement activity using {$method}.",
        ];
    }

    /**
     * @param  int[]  $branchIds
     */
    protected function inflows(int $organizationId, array $branchIds, CarbonInterface $start, CarbonInterface $end): float
    {
        $query = LoanRepayment::where('organization_id', $organizationId)
            ->where('status', LoanRepaymentStatus::Posted)
            ->whereBetween('payment_date', [$start, $end]);

        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        return round((float) $query->sum('amount'), 2);
    }

    /**
     * @param  int[]  $branchIds
     */
    protected function outflows(int $organizationId, array $branchIds, CarbonInterface $start, CarbonInterface $end): float
    {
        $query = Loan::where('organization_id', $organizationId)
            ->whereIn('status', [
                LoanStatus::Active->value,
                LoanStatus::Disbursed->value,
                LoanStatus::Completed->value,
            ])
            ->whereNotNull('disbursement_date')
            ->whereBetween('disbursement_date', [$start, $end]);

        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        return round((float) $query->sum('disbursed_amount'), 2);
    }

    /**
     * @return array<string, mixed>
     */
    protected function insufficient(int $organizationId, string $currency, int $observationCount, CarbonImmutable $today): array
    {
        $minimum = max(1, (int) config('predictive-intelligence.minimum_history_periods', 3));
        $months = max(1, (int) config('predictive-intelligence.history_months', 12));

        return [
            'status' => 'insufficient_data',
            'method' => 'naive',
            'target_period' => $today->addMonth()->format('Y-m'),
            'data_through' => $today->toDateString(),
            'data_from' => $this->quality->dataFrom($today, $months),
            'horizon' => 3,
            'confidence' => 'low',
            'data_quality' => 'insufficient',
            'observation_count' => $observationCount,
            'value_total' => null,
            'currency' => $currency,
            'series' => [],
            'factors' => [
                "No cash-flow forecast possible for organization {$organizationId}: only {$observationCount} complete month(s) of activity observed (at least {$minimum} required).",
            ],
            'assumptions' => [
                'insufficient_reason' => 'observation_count_below_minimum',
            ],
            'explanation' => 'Insufficient historical cash activity to produce a reliable forecast.',
        ];
    }
}
