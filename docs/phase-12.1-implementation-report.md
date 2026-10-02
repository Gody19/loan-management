# Phase 12.1 — Implementation Report (15 Parts)

**Feature:** Scheduled Management Reporting & Executive Intelligence Digests
**Baseline commit:** `4103d49` (Phase 12.0) → next `HEAD` (Phase 12.1)
**Status:** COMPLETE — implementation, tests, lint, regression and docs shipped.

---

### Part 1 — Strategy & scope guard

Recurring delivery of the six Phase 12.0 management reports. A schedule is a read-only distribution mechanism: it never computes a figure, decides anything, mutates a loan/member/journal entry, or widens the authority of its creator. Frequencies are a closed vocabulary — there is no user-supplied cron. Only completed reporting periods are ever produced. No new prediction/insight engine, no new inbox, no queue, and no Phase 13 work. The AI-contributed part of a delivery (the optional narrative) is bounded and labelled advisory prose.

### Part 2 — Completed-period engine extension

`app/Enums/ReportPeriodType.php` gained `Yesterday` (`yesterday`) and `PreviousWeek` (`previous_week`) plus `isCompleted()`. `app/AI/Reporting/Services/ReportPeriodService.php` resolves both through the existing single Phase 12.0 engine, preserving comparison-window and `data_through` semantics. Every schedule frequency maps to a completed period (`daily→yesterday`, `weekly→previous_week`, `monthly→previous_month`, `quarterly→previous_quarter`), asserted by `test_every_schedule_frequency_maps_to_a_completed_period` and `test_in_progress_periods_are_not_completed`.

### Part 3 — Frequency & recipient vocabularies

`ReportScheduleFrequency` (`daily|weekly|monthly|quarterly`, `periodType()`, `requiresWeekday()`, `requiresDayOfMonth()`, `nextRunAfter()`), `ReportScheduleRecipientMode` (`organization_managers|branch_managers|specific_users`) and `ReportScheduleRunStatus` (`running|completed|failed|skipped`). `nextRunAfter()` moves strictly forward in the schedule timezone and clamps a day beyond the month's real length with `addMonthsNoOverflow`.

### Part 4 — Persistence layer

Migrations `2026_10_01_000001_create_ai_report_schedules_table` and `2026_10_01_000002_create_ai_report_schedule_runs_table` (both applied) create `ai_report_schedules` (configuration only) and `ai_report_schedule_runs` (one row per execution, unique `execution_key`). Models `AiReportSchedule` and `AiReportScheduleRun` cast enums; `run_time` is a plain `H:i:s` string, never a datetime. `AiReportScheduleRun::executionKeyFor()` is the single definition of "the same run". A MySQL safe single-non-null-timestamp migration pattern is used throughout.

### Part 5 — Schedule lifecycle & next-run determinism

`AiReportScheduleService` implements `create`, `update`, `setActive` (enable/disable), `delete`, `listFor`, `findManageable`, `resolveRecipients`, `candidateRecipients`. Branch narrowing must be inside the trusted branch scope and belong to the schedule's own organization; recipients must be currently authorized users of that organization/branch. Timing changes recompute `next_run_at` strictly forward; disabling clears it; re-enabling recomputes from now. Every transition is audited.

### Part 6 — RBAC

New capability `ai.reports.schedule`, registered in `RolePermissionSeeder`, granted only to Organization Administrator, Branch Manager and Auditor — deliberately separate from read-only `ai.reports.view`. Managing a schedule additionally requires `ai.reports.view`; an accounting schedule requires `ai.accounting.view` at creation. Secretary and VICOBA Member are denied the entire surface. Asserted by `test_scheduling_roles_hold_the_capability_and_read_only_roles_do_not`, `test_vicoba_member_and_secretary_are_denied_the_whole_scheduling_surface`, `test_creating_a_schedule_requires_the_scheduling_capability`, `test_accounting_report_schedule_requires_the_accounting_capability` and `test_a_super_administrator_may_schedule_the_accounting_report`.

### Part 7 — Idempotent execution runner

`AiReportScheduleRunner::run()` resolves the completed period, derives the execution key, and **claims a `running` row before any generation**. A collision returns the existing run flagged `replayed` (an idempotent skip), while a non-key unique violation is rethrown. The scheduler and "Run now" share this single path. Covering tests: `test_a_due_schedule_produces_a_completed_report_and_a_run`, `test_running_the_same_period_twice_produces_only_one_report`, `test_a_manual_run_uses_the_idempotent_path_and_never_modifies_the_schedule`, `test_the_execution_key_is_deterministic_per_schedule_and_period`.

### Part 8 — Quiet failure isolation & audit

Generation runs under a context derived from the schedule owner and narrowed to the schedule's organization/branch, via `AiIntelligenceReportService::generate(..., asOf: now(schedule timezone))`. If the owner lost the capability/organization/branch, the run fails with a controlled diagnostic instead of escalating. One broken schedule never aborts the pass; every attempt advances exactly one period. Audited: `executed`, `failed`, `duplicate_suppressed`, `recipients_truncated`. Covering tests: `test_execution_fails_when_the_owner_loses_the_reporting_capability`, `test_one_failing_schedule_does_not_abort_the_other_schedules`.

### Part 9 — Delivery & executive digest

`ReportScheduleDigestService` builds a deterministic headline/summary from the report's own facts, advisories and data-quality notes, preserving classifications verbatim via `ReportDatumClassification::groupKey()`, and appends a bounded, explicitly labelled narrative excerpt. `deliver()` re-authorizes every recipient with `AiIntelligenceReportService::findAuthorized()` against the concrete report before creating any notification, and caps delivery at `max_recipients_per_run`. Covering tests: `test_only_currently_authorized_recipients_are_delivered_to`, `test_delivery_notifies_each_authorized_recipient_once`, `test_a_branch_scoped_schedule_only_notifies_the_branch`, `test_disabling_notification_still_produces_the_report`, `test_the_digest_preserves_classification_and_labels_the_narrative_as_advisory`.

### Part 10 — Dashboard surface & actions

`AiReportScheduleController` (store/update/toggle/runNow/destroy) is a thin HTTP layer gated by `permission:ai.reports.schedule` + `throttle:ai.tool`; it performs no tenant or scheduling arithmetic. The Phase 12.0 dashboard (`GET /ai/reports`) lists schedules and recent runs and hosts create/edit modals via `resources/views/ai/reports/partials/schedules.blade.php` and `schedule-form.blade.php`. Covering tests: `test_store_creates_a_schedule_over_http`, `test_store_rejects_an_accounting_schedule_without_the_accounting_capability`, `test_schedule_routes_are_capability_gated`, `test_run_now_reports_an_idempotent_replay`, `test_the_dashboard_lists_only_schedules_within_the_trusted_scope`.

### Part 11 — Notifications & inbox reuse

`AiScheduledReportNotification` is a database notification routed through the existing global Inbox (`/notifications`) — no second inbox. Its payload carries no financial figure of its own: it links to the persisted report and marks any narrative as advisory prose. Covering tests: `test_the_notification_links_to_the_persisted_report` and the delivery tests in Part 9.

### Part 12 — Scheduler & CLI

`AiRunReportSchedules` (`ai:run-report-schedules`, optional `--organization=`, `--limit=`) is a thin wrapper over the runner, returning success even when individual schedules fail (they are recorded, not surfaced as a pass failure). Registered in `routes/console.php` as `hourlyAt('05')->withoutOverlapping()`. Covering tests: `test_the_command_runs_due_schedules`, `test_the_command_is_a_noop_when_nothing_is_due`, `test_the_command_respects_the_organization_filter`, `test_the_command_is_a_noop_when_scheduling_is_disabled`.

### Part 13 — Security & tenant integrity

Organization, branch and recipients always come from authoritative state, never from the request. A foreign schedule is denied without disclosure; a foreign branch cannot be scheduled even by a user who belongs to both organizations; explicit recipients must be currently authorized users of the schedule's own scope. Scheduled runs execute under a narrowed owner-derived context and never escalate. The scheduler is read-only over business data. Covering tests: `test_a_foreign_schedule_is_not_disclosed`, `test_branch_of_another_organization_cannot_be_scheduled`, `test_specific_recipients_must_be_authorized_users`, `test_specific_recipient_mode_requires_at_least_one_recipient`, `test_scheduling_never_mutates_a_business_record`.

### Part 14 — Tests

`tests/Feature/AI/AiScheduledReportingTest.php`: **42 tests / 138 assertions, 0 failures, 0 skips** — completed-period engine (including schedule-timezone resolution), deterministic forward-only next-run and month-length clamp, capability matrix and denials, tenant/branch isolation, schedule CRUD/authz/audit, idempotency (replay, manual-run immutability), owner-authority failure, failure isolation, recipient re-authorization and branch scoping, digest classification preservation, command behavior (due/no-op/org filter/disabled), HTTP routes and RBAC, dashboard scoping, and non-mutation of business records.

### Part 15 — Lint, regression & docs

`vendor/bin/pint --dirty` applied; `php -l` green on all touched files. Regression: `AiIntelligenceReportingTest` re-run green — **53 tests / 631 assertions**. Combined Phase 12.0 + 12.1: **95 tests / 769 assertions, 0 failures**. `route:list --name=ai.reports` shows 10 routes (5 new). `docs/phase-12.1-scheduled-management-reporting.md` and this report document the phase. Phase 11.8/11.9 and the full repository suite were **not** re-run in this session; only the directly relevant Phase 12.0 suite was exercised as regression.

---

**Outcome:** Phase 12.1 is implemented, tested, formatted and documented, ready to commit. Three defects were discovered by the new tests and fixed in-place (see the accompanying notes). Phase 13 remains explicitly out of scope.
