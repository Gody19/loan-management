<?php

namespace Tests\Unit;

use App\AI\PredictiveIntelligence\StatisticalForecastService;
use Tests\TestCase;

/**
 * Phase 11.8 statistical baseline: pure, deterministic arithmetic behind the
 * predictive layer. These are unit tests of the math itself, independent of
 * any loan or repayment fixtures.
 */
class PredictiveStatisticalForecastTest extends TestCase
{
    private StatisticalForecastService $statistics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statistics = new StatisticalForecastService;
    }

    public function test_moving_average_returns_last_window_mean(): void
    {
        $this->assertSame(35.0, $this->statistics->movingAverage([10, 20, 30, 40], 2));
        $this->assertSame(25.0, $this->statistics->movingAverage([10, 20, 30, 40], 4));
    }

    public function test_moving_average_is_null_when_window_is_bigger_than_history(): void
    {
        $this->assertNull($this->statistics->movingAverage([10, 20], 3));
        $this->assertNull($this->statistics->movingAverage([], 1));
    }

    public function test_trend_slope_is_positive_for_an_increasing_series(): void
    {
        $this->assertSame(100.0, $this->statistics->trendSlope([100, 200, 300]));
    }

    public function test_trend_slope_is_null_for_a_single_point(): void
    {
        $this->assertNull($this->statistics->trendSlope([100]));
    }

    public function test_naive_forecast_repeats_the_last_value(): void
    {
        $this->assertSame([10.0, 10.0, 10.0], $this->statistics->forecastNext(3, [10.0], 'naive'));
    }

    public function test_linear_trend_forecast_continues_the_fitted_line(): void
    {
        $this->assertSame(
            [140.0, 150.0],
            $this->statistics->forecastNext(2, [100.0, 110.0, 120.0, 130.0], 'linear_trend'),
        );
    }

    public function test_moving_average_forecast_steps_the_rolling_mean_forward(): void
    {
        config(['predictive-intelligence.moving_average_window' => 3]);

        $forecast = $this->statistics->forecastNext(2, [110.0, 120.0, 130.0], 'moving_average');

        $this->assertCount(2, $forecast);
        $this->assertEqualsWithDelta(120.0, $forecast[0], 0.0001);
        $this->assertEqualsWithDelta(123.33, $forecast[1], 0.01);
    }

    public function test_method_for_scarce_history_is_naive_with_low_confidence(): void
    {
        config([
            'predictive-intelligence.minimum_history_periods' => 3,
            'predictive-intelligence.good_history_periods' => 6,
        ]);

        $result = $this->statistics->methodFor(2, [100.0, 100.0]);

        $this->assertSame(['method' => 'naive', 'confidence' => 'low'], $result);
    }

    public function test_method_for_limited_history_is_moving_average_with_medium_confidence(): void
    {
        config([
            'predictive-intelligence.minimum_history_periods' => 3,
            'predictive-intelligence.good_history_periods' => 6,
        ]);

        $result = $this->statistics->methodFor(4, [100.0, 110.0, 120.0, 130.0]);

        $this->assertSame(['method' => 'moving_average', 'confidence' => 'medium'], $result);
    }

    public function test_method_for_deep_smooth_history_is_linear_trend_with_high_confidence(): void
    {
        config([
            'predictive-intelligence.minimum_history_periods' => 3,
            'predictive-intelligence.good_history_periods' => 6,
            'predictive-intelligence.high_dispersion_threshold' => 0.35,
        ]);

        $result = $this->statistics->methodFor(8, [100.0, 110.0, 120.0, 130.0, 140.0, 150.0, 160.0, 170.0]);

        $this->assertSame(['method' => 'linear_trend', 'confidence' => 'high'], $result);
    }

    public function test_method_for_deep_but_noisy_history_downgrades_confidence(): void
    {
        config([
            'predictive-intelligence.minimum_history_periods' => 3,
            'predictive-intelligence.good_history_periods' => 6,
            'predictive-intelligence.high_dispersion_threshold' => 0.35,
        ]);

        $result = $this->statistics->methodFor(8, [100.0, 500.0, 100.0, 500.0, 100.0, 500.0, 100.0, 500.0]);

        $this->assertSame(['method' => 'linear_trend', 'confidence' => 'medium'], $result);
    }

    public function test_residual_dispersion_is_zero_for_a_perfect_line(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->statistics->residualDispersion([100.0, 200.0, 300.0, 400.0]), 0.0001);
    }
}
