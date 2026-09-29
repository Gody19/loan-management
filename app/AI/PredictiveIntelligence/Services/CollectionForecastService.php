<?php

namespace App\AI\PredictiveIntelligence\Services;

use App\AI\PredictiveIntelligence\DataQualityService;
use App\AI\PredictiveIntelligence\StatisticalForecastService;
use App\Enums\LoanRepaymentStatus;
use App\Enums\PredictiveDataQuality;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Collection forecast (Phase 11.8, §34).
 *
 * Complete months of scheduled due totals and posted collections (the same
 * authoritative repayment records used by Phase 11.7) feed baselines for
 * expected collections, scheduled due and the overdue workload that trails at
 * the end of each month. The outlook is advisory — it never adjusts a member
 * obligation, accrual or ledger balance.
 */
class CollectionForecastService
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

            $due = $this->due($organizationId, $branchIds, $start, $end);
            $collected = $this->collected($organizationId, $branchIds, $start, $end);
            $overdueWorkload = $this->overdueWorkload($organizationId, $branchIds, $end);

            if ($due > 0 || $collected > 0) {
                $activityMonths++;
            }

            $series[] = [
                'period' => $month->format('Y-m'),
                'due' => $due,
                'collected' => $collected,
                'collection_rate' => $due > 0 ? round(($collected / $due) * 100, 2) : 0,
                'overdue_loans' => $overdueWorkload['loans'],
                'overdue_amount' => $overdueWorkload['amount'],
                'is_forecast' => false,
            ];
        }

        $observationCount = $activityMonths;
        $grade = $this->quality->grade($observationCount);

        if ($grade === PredictiveDataQuality::Insufficient) {
            return $this->insufficient($organizationId, $currency, $observationCount, $today);
        }

        $expanded = $this->quality->trimLeadingInactive($series, ['due', 'collected']);
        $collectedValues = array_map(fn (array $row) => (float) $row['collected'], $expanded !== [] ? $expanded : $series);
        $dueValues = array_map(fn (array $row) => (float) $row['due'], $expanded !== [] ? $expanded : $series);

        $collectedMethod = $this->statistics->methodFor($observationCount, $collectedValues);
        $dueMethod = $this->statistics->methodFor($observationCount, $dueValues);

        $method = in_array($collectedMethod['method'], ['linear_trend', 'moving_average'], true)
            ? $collectedMethod['method']
            : $dueMethod['method'];
        $confidence = $collectedMethod['confidence'] === 'high' && $dueMethod['confidence'] === 'high'
            ? 'high'
            : ($collectedMethod['confidence'] === 'low' || $dueMethod['confidence'] === 'low' ? 'low' : 'medium');

        $forecastCollected = $this->statistics->forecastNext($horizon, $collectedValues, $method);
        $forecastDue = $this->statistics->forecastNext($horizon, $dueValues, $method);

        $forecast = [];

        foreach (array_values($forecastCollected) as $index => $collected) {
            $dueForecast = $forecastDue[$index];
            $collectedRounded = round($collected, 2);
            $dueRounded = round($dueForecast, 2);

            $forecast[] = [
                'period' => $today->addMonths($index + 1)->format('Y-m'),
                'due' => $dueRounded,
                'collected' => $collectedRounded,
                'collection_rate' => $dueRounded > 0 ? round(($collectedRounded / $dueRounded) * 100, 2) : 0,
                'overdue_loans' => null,
                'overdue_amount' => null,
                'is_forecast' => true,
            ];
        }

        $valueTotal = (float) end($forecast)['collected'];
        $latestBacklog = (float) end($series)['overdue_amount'];

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
                "Projected collections {$horizon} month(s) ahead: ".number_format($valueTotal, 2)." {$currency}.",
                'Overdue workload at the end of the last complete month: '.number_format($latestBacklog, 2)." {$currency} across ".(int) end($series)['overdue_loans'].' loan(s).',
            ],
            'assumptions' => [
                'method' => $method,
                'confidence' => $confidence,
                'observation_count' => $observationCount,
                'forecast_horizon' => $horizon,
                'incomplete_period_excluded' => $this->quality->incompletePeriodKey(),
                'scope_note' => 'Collections are posted repayments; due is scheduled installment totals; overdue workload is outstanding schedules due at or before each month end.',
            ],
            'explanation' => "Collection forecast extrapolated from {$observationCount} complete month(s) of scheduled due and posted collections using {$method}.",
        ];
    }

    /**
     * @param  int[]  $branchIds
     */
    protected function due(int $organizationId, array $branchIds, CarbonInterface $start, CarbonInterface $end): float
    {
        $query = LoanRepaymentSchedule::where('organization_id', $organizationId)
            ->whereBetween('due_date', [$start, $end]);

        if ($branchIds !== []) {
            $query->whereHas('loan', fn ($loan) => $loan->whereIn('branch_id', $branchIds));
        }

        return round((float) $query->sum('total_amount'), 2);
    }

    /**
     * @param  int[]  $branchIds
     */
    protected function collected(int $organizationId, array $branchIds, CarbonInterface $start, CarbonInterface $end): float
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
     * Schedules still outstanding with a due date at or before the month end:
     * the stock of overdue work that would trail into the following month.
     *
     * @param  int[]  $branchIds
     * @return array{loans: int, amount: float}
     */
    protected function overdueWorkload(int $organizationId, array $branchIds, CarbonInterface $end): array
    {
        $query = LoanRepaymentSchedule::where('organization_id', $organizationId)
            ->where('outstanding_amount', '>', 0)
            ->where('due_date', '<=', $end);

        if ($branchIds !== []) {
            $query->whereHas('loan', fn ($loan) => $loan->whereIn('branch_id', $branchIds));
        }

        $amount = round((float) $query->sum('outstanding_amount'), 2);
        $loans = (int) $query->distinct()->count('loan_id');

        return ['loans' => $loans, 'amount' => $amount];
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
                "No collection forecast possible for organization {$organizationId}: only {$observationCount} complete month(s) of due/collected activity observed (at least {$minimum} required).",
            ],
            'assumptions' => [
                'insufficient_reason' => 'observation_count_below_minimum',
            ],
            'explanation' => 'Insufficient historical collection activity to produce a reliable forecast.',
        ];
    }
}
