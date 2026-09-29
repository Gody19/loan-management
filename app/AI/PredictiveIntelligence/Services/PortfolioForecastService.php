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
 * Portfolio forecast (Phase 11.8, §22).
 *
 * Historical (complete-month) series of disbursements and principal repaid
 * builds a running outstanding balance; the statistical baseline extrapolates
 * the month-over-month change of that balance forward. Output is an advisory
 * indication only — nothing computed here feeds disbursement, eligibility or
 * accounting rules.
 */
class PortfolioForecastService
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

        $cumulativeDisbursed = 0.0;
        $cumulativeRepaid = 0.0;
        $series = [];
        $activityMonths = 0;

        for ($offset = $months; $offset >= 1; $offset--) {
            $month = $today->startOfMonth()->subMonths($offset);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $monthDisbursed = $this->disbursed($organizationId, $branchIds, $start, $end);
            $monthRepaid = $this->repaid($organizationId, $branchIds, $start, $end);

            $cumulativeDisbursed = round($cumulativeDisbursed + $monthDisbursed, 2);
            $cumulativeRepaid = round($cumulativeRepaid + $monthRepaid, 2);
            $outstanding = round($cumulativeDisbursed - $cumulativeRepaid, 2);

            if ($monthDisbursed > 0 || $monthRepaid > 0) {
                $activityMonths++;
            }

            $series[] = [
                'period' => $month->format('Y-m'),
                'disbursements' => $cumulativeDisbursed,
                'principal_repaid' => $cumulativeRepaid,
                'outstanding_end' => $outstanding,
                'is_forecast' => false,
            ];
        }

        $observationCount = $activityMonths;
        $grade = $this->quality->grade($observationCount);

        if ($grade === PredictiveDataQuality::Insufficient) {
            return $this->insufficient($organizationId, $currency, $observationCount, $today);
        }

        $expanded = $this->quality->trimLeadingInactive($series, ['disbursements', 'principal_repaid']);
        $deltas = $this->monthlyDeltas($expanded !== [] ? $expanded : $series);
        $methodConfidence = $this->statistics->methodFor($observationCount, $deltas);
        $method = $methodConfidence['method'];
        $confidence = $methodConfidence['confidence'];

        $forecastDeltas = $this->statistics->forecastNext($horizon, $deltas, $method);

        $forecast = [];
        $running = (float) end($series)['outstanding_end'];

        foreach (array_values($forecastDeltas) as $index => $delta) {
            $running = max(0.0, round($running + $delta, 2));
            $forecast[] = [
                'period' => $today->addMonths($index + 1)->format('Y-m'),
                'outstanding_end' => $running,
                'is_forecast' => true,
            ];
        }

        $valueTotal = (float) end($forecast)['outstanding_end'];

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
                'Outstanding balance ends the last complete month at '.number_format((float) end($series)['outstanding_end'], 2)." {$currency}.",
                "Projected outstanding balance {$horizon} month(s) ahead: ".number_format($valueTotal, 2)." {$currency}.",
            ],
            'assumptions' => [
                'method' => $method,
                'confidence' => $confidence,
                'observation_count' => $observationCount,
                'forecast_horizon' => $horizon,
                'incomplete_period_excluded' => $this->quality->incompletePeriodKey(),
                'survivorship' => 'Statuses active, disbursed and completed are included; cancelled and approved-but-not-disbursed are not.',
            ],
            'explanation' => "Portfolio forecast extrapolated from {$observationCount} complete month(s) of disbursement and principal-repayment activity using {$method}.",
        ];
    }

    /**
     * @param  int[]  $branchIds
     */
    protected function disbursed(int $organizationId, array $branchIds, CarbonInterface $start, CarbonInterface $end): float
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
     * @param  int[]  $branchIds
     */
    protected function repaid(int $organizationId, array $branchIds, CarbonInterface $start, CarbonInterface $end): float
    {
        $query = LoanRepayment::where('organization_id', $organizationId)
            ->where('status', LoanRepaymentStatus::Posted)
            ->whereBetween('payment_date', [$start, $end]);

        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        return round((float) $query->sum('principal_portion'), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $series
     * @return float[]
     */
    protected function monthlyDeltas(array $series): array
    {
        $deltas = [];

        foreach ($series as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $deltas[] = round((float) $row['outstanding_end'] - (float) $series[$index - 1]['outstanding_end'], 2);
        }

        return $deltas;
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
                "No forecast possible for organization {$organizationId}: only {$observationCount} complete month(s) of disbursement/repayment activity observed (at least {$minimum} required).",
            ],
            'assumptions' => [
                'insufficient_reason' => 'observation_count_below_minimum',
            ],
            'explanation' => 'Insufficient historical portfolio activity to produce a reliable forecast.',
        ];
    }
}
