<?php

namespace App\AI\PredictiveIntelligence;

use InvalidArgumentException;

/**
 * Deterministic statistical baseline (Phase 11.8).
 *
 * Pure, fully reproducible arithmetic used by every predictive domain: moving
 * average, least-squares trend slope and the three forecast methods (naive,
 * moving average, linear trend). No randomness, no caching of transient
 * state, no provider involvement. All amounts are rounded to the reporting
 * currency's cents at the call site so statistical smoothing never invents
 * sub-cent precision.
 */
final class StatisticalForecastService
{
    /**
     * Rolling average of the last $window values. Null when history is too
     * short for the window.
     *
     * @param  float[]  $values
     */
    public function movingAverage(array $values, int $window): ?float
    {
        if ($window < 1 || count($values) < $window) {
            return null;
        }

        $slice = array_slice(array_values($values), -$window);

        return array_sum($slice) / count($slice);
    }

    /**
     * Least-squares slope over equally spaced observations (x = 0..n-1).
     * Null when there is not enough history to fit a line.
     *
     * @param  float[]  $values
     */
    public function trendSlope(array $values): ?float
    {
        $values = array_values($values);
        $n = count($values);

        if ($n < 2) {
            return null;
        }

        $meanX = ($n - 1) / 2;
        $meanY = array_sum($values) / $n;

        $numerator = 0.0;
        $denominator = 0.0;

        foreach ($values as $index => $value) {
            $numerator += ($index - $meanX) * ($value - $meanY);
            $denominator += ($index - $meanX) ** 2;
        }

        if ($denominator === 0.0) {
            return null;
        }

        return $numerator / $denominator;
    }

    /**
     * Residual dispersion = coefficient of variation of the deviations from
     * the fitted line. Used to downgrade confidence when history is noisy.
     *
     * @param  float[]  $values
     */
    public function residualDispersion(array $values): float
    {
        $values = array_values($values);
        $n = count($values);

        if ($n < 3) {
            return 0.0;
        }

        $meanX = ($n - 1) / 2;
        $slope = $this->trendSlope($values);
        $intercept = (array_sum($values) / $n) - ($slope ?? 0.0) * $meanX;

        $numerator = 0.0;

        foreach ($values as $index => $value) {
            $fitted = $intercept + ($slope ?? 0.0) * $index;
            $numerator += ($value - $fitted) ** 2;
        }

        $residualStandardDeviation = sqrt($numerator / $n);
        $mean = array_sum($values) / $n;

        if (abs($mean) < 0.0000001) {
            return 1.0;
        }

        return $residualStandardDeviation / abs($mean);
    }

    /**
     * Extrapolate $count future values from $historical by the given method.
     * "naive" repeats the last observation; "moving_average" steps the rolling
     * mean forward; "linear_trend" continues the fitted line from the last
     * observation.
     *
     * @param  float[]  $historical
     * @return float[]
     */
    public function forecastNext(int $count, array $historical, string $method): array
    {
        if ($count < 1) {
            return [];
        }

        $historical = array_values($historical);

        if ($historical === []) {
            return array_fill(0, $count, 0.0);
        }

        $window = max(1, (int) config('predictive-intelligence.moving_average_window', 3));

        switch ($method) {
            case 'naive':
                return array_fill(0, $count, (float) end($historical));

            case 'moving_average':
                return $this->movingAverageForecast($historical, $window, $count);

            case 'linear_trend':
                $slope = $this->trendSlope($historical) ?? 0.0;
                $last = (float) end($historical);

                return array_map(
                    fn (int $step) => $last + $slope * $step,
                    range(1, $count),
                );

            default:
                throw new InvalidArgumentException("Unknown forecast method [{$method}].");
        }
    }

    /**
     * Pick the statistical method and confidence for a given history depth.
     * More trustworthy history upgrades the baseline; dispersion can downgrade
     * the confidence label of an otherwise good fit.
     *
     * @return array{method: string, confidence: string}
     */
    public function methodFor(int $observationCount, array $observations): array
    {
        $minimum = max(1, (int) config('predictive-intelligence.minimum_history_periods', 3));
        $good = max($minimum, (int) config('predictive-intelligence.good_history_periods', 6));

        if ($observationCount < $minimum) {
            return ['method' => 'naive', 'confidence' => 'low'];
        }

        if ($observationCount >= $good) {
            $dispersion = $this->residualDispersion($observations);
            $highDispersion = (float) config('predictive-intelligence.high_dispersion_threshold', 0.35);

            return [
                'method' => 'linear_trend',
                'confidence' => $dispersion > $highDispersion ? 'medium' : 'high',
            ];
        }

        return ['method' => 'moving_average', 'confidence' => 'medium'];
    }

    /**
     * Step-forward rolling mean. Each new value joins the tail before the next
     * step, exactly like a moving-average baseline on a live stream.
     *
     * @param  float[]  $historical
     * @return float[]
     */
    protected function movingAverageForecast(array $historical, int $window, int $count): array
    {
        $rolling = array_slice($historical, -(min($window, count($historical))));
        $forecast = [];

        for ($step = 0; $step < $count; $step++) {
            $next = array_sum($rolling) / count($rolling);
            $forecast[] = $next;
            $rolling[] = $next;

            if (count($rolling) > $window) {
                array_shift($rolling);
            }
        }

        return $forecast;
    }
}
