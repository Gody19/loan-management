<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Management Action follow-up (Phase 12.3)
    |--------------------------------------------------------------------------
    |
    | Management actions are human workflow records created around the existing
    | advisory intelligence. These settings bound their presentation only; they
    | never change an action's state or execute a business operation.
    |
    */

    // An open action whose due date falls within this many days (inclusive) is
    // presented as "due soon". Purely a display threshold.
    'due_soon_days' => 3,

    // The Action Center list page size.
    'index_per_page' => 20,

    // The number of recently completed actions shown on the executive review
    // follow-up panel.
    'recent_completed_limit' => 5,

];
