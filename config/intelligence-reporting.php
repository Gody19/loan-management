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
];