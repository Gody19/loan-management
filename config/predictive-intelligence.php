<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Predictive Intelligence (Phase 11.8)
    |--------------------------------------------------------------------------
    |
    | Deterministic, read-only statistical baselines for the Predictive
    | Intelligence layer. Predictions are advisory indications only: they are
    | computed from historical FinancePro records inside the trusted tenant
    | scope, never influence loan eligibility/approval/rates or accounting,
    | and never expose member-level detail. Every knob is configurable here;
    | changing a threshold changes only what the advisory layer reports.
    |
    */

    'currency' => env('APP_CURRENCY', 'TZS'),

    /*
    | Version string stamped on every generated insight. Bump it when the
    | statistical methodology changes so historical predictions are
    | identifiable and comparable (each version keeps its own snapshot rows).
    */

    'model_version' => 'statistical-baseline-v1',

    /*
    | Number of trailing COMPLETE months used as forecast inputs. The current
    | (in-progress) month is never included in a series — incomplete periods
    | are excluded from the inputs, never extrapolated.
    */

    'history_months' => (int) env('PREDICTIVE_HISTORY_MONTHS', 12),

    /*
    | Data-sufficiency gates (number of complete monthly observations).
    | Below minimum_history_periods the insight is stored as insufficient_data;
    | at/above good_history_periods the statistical method upgrades to the
    | linear-trend baseline with high confidence.
    */

    'minimum_history_periods' => (int) env('PREDICTIVE_MIN_HISTORY_PERIODS', 3),
    'good_history_periods' => (int) env('PREDICTIVE_GOOD_HISTORY_PERIODS', 6),

    /*
    | How many future months the baselines extrapolate. Capped by max_horizon.
    */

    'default_horizon' => (int) env('PREDICTIVE_DEFAULT_HORIZON', 3),
    'max_horizon' => (int) env('PREDICTIVE_MAX_HORIZON', 6),

    /*
    | Statistical method selection:
    |   naive            last observed value repeated (least history -> lowest confidence)
    |   moving_average   rolling mean of the latest observations
    |   linear_trend     least-squares slope extrapolation (most history -> highest confidence)
    */

    'methods' => [
        'naive' => 'naive',
        'moving_average' => 'moving_average',
        'linear_trend' => 'linear_trend',
    ],

    /*
    | Moving-average window used when history is limited.
    */

    'moving_average_window' => (int) env('PREDICTIVE_MA_WINDOW', 3),

    /*
    | Residual dispersion (coefficient of variation of the fitted residuals)
    | above which the confidence label is downgraded one step.
    */

    'high_dispersion_threshold' => (float) env('PREDICTIVE_HIGH_DISPERSION', 0.35),

    /*
    | Delinquency-risk indicator banding: the composite 0-100 indicator map
    | to low (under medium_threshold), medium and high risk levels.
    */

    'delinquency_risk_levels' => [
        'medium_threshold' => (float) env('PREDICTIVE_RISK_MEDIUM_THRESHOLD', 34.0),
        'high_threshold' => (float) env('PREDICTIVE_RISK_HIGH_THRESHOLD', 67.0),
    ],

    /*
    | A generated insight is considered stale when new observable activity
    | (posted repayment, disbursement) is dated after its data snapshot.
    |
    */

    'stale_after_days' => (int) env('PREDICTIVE_STALE_AFTER_DAYS', 7),

];
