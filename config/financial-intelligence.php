<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Financial Intelligence (Phase 11.7)
    |--------------------------------------------------------------------------
    |
    | Deterministic, read-only analytical knobs for the Financial Intelligence
    | layer. Every threshold is configurable here; services never hard-code the
    | numbers. Values come exclusively from environment variables. Changing a
    | threshold never changes business rules — it only changes what the
    | descriptive/diagnostic layer reports.
    |
    */

    /*
    | Reporting currency carried on every intelligence figure.
    */

    'currency' => env('APP_CURRENCY', 'TZS'),

    /*
    | Standard delinquency window (days) for PAR reporting. Below this a loan
    | is treated as current even when it has days past due.
    */

    'par_threshold_days' => (int) env('FI_PAR_THRESHOLD_DAYS', 30),

    /*
    | Number of trailing months included in trend series.
    */

    'trend_months' => (int) env('FI_TREND_MONTHS', 12),

    /*
    | Deterministic rule-based anomaly detection. The enabled switch controls
    | detection runs; rule thresholds bound what is flagged. Nothing here can
    | change or reverse financial records — detection is a pure read with an
    | audited review trail.
    */

    'anomaly_detection' => [
        'enabled' => (bool) env('FI_ANOMALY_ENABLED', true),

        // How far back repayment activity is inspected for unusual patterns.
        'detection_window_days' => (int) env('FI_ANOMALY_WINDOW_DAYS', 30),

        // Upper bound of findings returned/persisted per detection pass.
        'max_findings' => (int) env('FI_ANOMALY_MAX_FINDINGS', 50),

        // A posted repayment exceeding this multiple of its expected
        // installment is flagged as an unusual large overpayment.
        'unusual_large_overpayment_ratio' => (float) env('FI_OVERPAYMENT_RATIO', 1.5),

        // A loan with at least this many reversed repayments inside the
        // detection window is flagged for repeated reversals.
        'repeated_reversal_min_count' => (int) env('FI_REPEATED_REVERSAL_MIN', 3),

        // A single delinquent loan holding >= this share of total delinquent
        // principal is flagged as delinquent concentration.
        'delinquency_concentration_ratio' => (float) env('FI_DELINQUENCY_CONCENTRATION_RATIO', 0.5),

        // Delinquent principal (past the PAR threshold) reaching this percent
        // of total principal outstanding flags PAR concentration.
        'par_concentration_threshold_percent' => (float) env('FI_PAR_CONCENTRATION_PERCENT', 10),

        // A single loan holding >= this share of total active outstanding
        // principal is flagged as loan concentration.
        'loan_concentration_ratio' => (float) env('FI_LOAN_CONCENTRATION_RATIO', 0.25),

        // Combined cash-on-hand / bank / mobile-money balance below this
        // amount flags a negative (illiquid) cash position.
        'negative_cash_threshold' => (float) env('FI_NEGATIVE_CASH_THRESHOLD', 0),
    ],

];
