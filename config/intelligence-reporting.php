<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Intelligence Reporting (Phase 12.0)
    |--------------------------------------------------------------------------
    |
    | Management intelligence reporting over the existing authoritative
    | FinancePro services. A report is assembled from the existing financial
    | reporting services, the Phase 11.8 predictive outlooks and the Phase
    | 11.9 proactive insights; every figure is computed deterministically and
    | the AI contributes an optional, clearly labelled narrative explanation
    | only. Reports never modify a business record, never make a decision and
    | never alter a prediction or an insight.
    |
    */

    'currency' => env('APP_CURRENCY', 'TZS'),

    /*
    |--------------------------------------------------------------------------
    | Report persistence
    |--------------------------------------------------------------------------
    |
    | Whether generated reports are persisted as ai_intelligence_reports rows.
    | Persistence keeps the generating status lifecycle (generating /
    | completed / failed) auditable. Disabling it makes generation fully
    | synchronous and in-memory only.
    */

    'persist' => (bool) env('AI_REPORTS_PERSIST', true),

    /*
    |--------------------------------------------------------------------------
    | AI narrative
    |--------------------------------------------------------------------------
    |
    | The narrative layer is optional. When requested it is generated over the
    | sanitized structured report context (facts, trends, predictive signals and
    | proactive insights) — never over unrestricted database access. Any
    | provider failure degrades gracefully: the authoritative report is still
    | delivered and the narrative is simply reported as unavailable.
    */

    'narrative' => [
        'enabled' => (bool) env('AI_REPORTS_NARRATIVE_ENABLED', true),
        'max_context_datums' => (int) env('AI_REPORTS_NARRATIVE_MAX_DATUMS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Period engine limits
    |--------------------------------------------------------------------------
    |
    | Guards against an unreasonable custom range: from must not be after to,
    | and the range may not exceed max_custom_range_days. The bound follows the
    | application's configured reporting horizon.
    */

    'max_custom_range_days' => (int) env('AI_REPORTS_MAX_RANGE_DAYS', 366),

    /*
    |--------------------------------------------------------------------------
    | Comparison
    |--------------------------------------------------------------------------
    |
    | Percentage change is only reported when the previous comparable period
    | has a measurable value. Zero is never substituted for missing data.
    */

    'comparison' => [
        'relative_change_minimum' => (float) env('AI_REPORTS_RELATIVE_MINIMUM', 0.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Section limits
    |--------------------------------------------------------------------------
    |
    | Caps the number of rows carried per section so a large organization can
    | never pull an entire portfolio into a report payload or an export.
    */

    'max_rows_per_section' => (int) env('AI_REPORTS_MAX_ROWS_PER_SECTION', 25),

    /*
    |--------------------------------------------------------------------------
    | Report history
    |--------------------------------------------------------------------------
    */

    'recent_limit' => (int) env('AI_REPORTS_RECENT_LIMIT', 25),

    /*
    |--------------------------------------------------------------------------
    | Scheduled reporting (Phase 12.1)
    |--------------------------------------------------------------------------
    |
    | Recurring management reports reuse the Phase 12.0 reporting service over
    | a *completed* period — the previous day, week, month or quarter — resolved
    | in each schedule's own timezone. Frequencies come from a closed
    | vocabulary; there is deliberately no cron column and no user-supplied
    | expression anywhere in the scheduling path.
    |
    | Delivery pushes into the existing Phase 11.9 in-app inbox, and every
    | recipient is re-authorized against the produced report immediately before
    | a notification row is created.
    |
    */

    'scheduling' => [
        'enabled' => (bool) env('AI_REPORT_SCHEDULES_ENABLED', true),

        // Whether a produced report notifies its audience at all. Disabling this
        // still generates and persists the report; it only stops delivery.
        'notify' => (bool) env('AI_REPORT_SCHEDULES_NOTIFY', true),

        // Upper bounds so one pass can never become an unbounded batch: at most
        // this many due schedules per tick, and at most this many recipients per
        // run (a truncated audience is audited).
        'max_runs_per_pass' => (int) env('AI_REPORT_SCHEDULES_MAX_RUNS', 25),
        'max_recipients_per_run' => (int) env('AI_REPORT_SCHEDULES_MAX_RECIPIENTS', 50),
        'max_schedules_listed' => (int) env('AI_REPORT_SCHEDULES_MAX_LISTED', 100),

        // How long one execution may hold its advisory lock. The unique
        // schedule+period execution key remains the authoritative idempotency
        // guard; the lock only prevents duplicated work.
        'lock_seconds' => (int) env('AI_REPORT_SCHEDULES_LOCK_SECONDS', 900),

        // Digest bounds. The deterministic summary is built from the report's
        // own classified rows; the optional AI excerpt is advisory prose and is
        // truncated to this many characters (0 disables the excerpt entirely).
        'digest_max_headline' => (int) env('AI_REPORT_SCHEDULES_DIGEST_HEADLINE', 6),
        'digest_max_narrative_chars' => (int) env('AI_REPORT_SCHEDULES_DIGEST_NARRATIVE_CHARS', 600),
    ],
];
