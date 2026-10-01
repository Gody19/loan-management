# Phase 11.9 — Implementation Report (15 Parts)

**Feature:** Proactive Financial Intelligence — Alerts & Actionable AI Insights
**Baseline commit:** `10acd81` (Phase 11.8) → next `HEAD` (Phase 11.9)
**Status:** COMPLETE — implementation, tests, lint, regression and docs shipped.

---

### Part 1 — Strategy & scope guard

Delivered advisory-only rules-based alerts; headers/labels everywhere (formatter, dashboard, tool, notifications) mark insights as advisory. No insight ever triggers an automated approval/eligibility/rate/ledger/repayment decision; no ML is trained or hosted; nothing reaches member portal or public landing page. Insight lifecycle is human-decided: `new → read → acknowledged → resolved` or `dismissed`.

### Part 2 — Rules engine

`ProactiveInsightDetectionService` runs ten deterministic rules over authoritative records: `overdue_loan`, `portfolio_concentration`, `maturity_pressure`, `portfolio_par`, `cashflow_shortfall`, `collection_decline`, `savings_decline`, `accounting_draft_entries` (+ unbalanced-trial-balance warning), `operational_pending_applications` (+ data-quality notice), and the predictive pair `predictive_delinquency_risk` / `predictive_quality_gate`. Each rule emits a stable dedup identity, title/summary/recommendation and a severity; branch filtering is supported and surfaced on every row via `source_type` + `source_id`.

### Part 3 — Configuration

`config/proactive-intelligence.php` centralizes all twenty-plus thresholds (`overdue_min_days`, `maturity_window_days`, `par_*`, `concentration_ratio`, `cashflow_shortfall_threshold`, `collection_decline_points`, `savings_decline_percent`, `journal_draft_days`, `operational_pending_days`, `stale_after_days`, `max_per_organization`) behind `AI_INSIGHTS_*` env keys, documented in `.env.example`. Changing a knob changes only what the advisory layer reports.

### Part 4 — Persistence layer

Migrated `ai_insights` (unique `dedup_key`, composite `(organization_id, status, generated_at)`, indexes on `type`/`severity`, morph object, user FKs nullOnDelete) and a standard `notifications` table. `AiInsight` model + four enums with dashboard-friendly labels, `priority()` (severity) and `isOpen()` (status). The duplicate `object_type/object_id` index that shadowed `nullableMorphs` was removed during this phase's migration fix.

### Part 5 — Lifecycle, dedup & expiration

`ProactiveInsightService::generate()` persists each raw under its dedup key with update-or-reopen semantics; dismissed rows are permanent for a condition; resolved/expired rows reopen as `new`; a unique constraint makes concurrent passes race-safe. Domain-driven expiration retires open insights that are absent from the current rule results **and** older than `stale_after_days`, bounding the open list without revoking human decisions.

### Part 6 — RBAC

Single capability `ai.insights.view` (read-only). Seeder grants it to the eight staff roles (Organization Administrator, Branch Manager, Loan Officer, Credit Officer, Collection Officer, Treasurer, Accountant, Auditor); Secretary and VICOBA Member are denied; Super Administrator receives everything. All dashboard/action/tool routes are protected by it.

### Part 7 — AI chat tool surface

`ProactiveInsightsTool` registered under `ai.insights.view`: user-org scope, zero-argument, read-only. Serializes id, org/branch, type/severity/status labels, title/summary/recommendation, source, period window, data-through, generated-at and `open` flag, with `open_count` and an explicit note that acknowledge/resolve/dismiss are human-only. Registry business capabilities: **21**.

### Part 8 — Formatter & controller routing

`AiInsightResultFormatter` emits a `<FINANCEPRO_INSIGHT_DATUM>` delimited block containing the insights summary and the advisory caveat. `AiController::formatResult()` routes the formatter for both the direct `/ai/tool` call and the orchestrated flow, so a tool response is never double-wrapped.

### Part 9 — Orchestration

Chat orchestrator gained keywords `insight`, `proactive`, `action item`, `priorit`, `focus on` on the Phase 11.9 entry, placed **last** in `ORGANIZATION_INTELLIGENCE` (Phase 11.7/11.8 matches win). Requests routed to the capability audit `ai.insights.view` as a completion event through the standard tool-execution path.

### Part 10 — Dashboard surface & actions

`/ai/intelligence` renders the "Proactive insights & alerts (Advisory)" card (org-scoped, severity-first, empty-state when the holder has no data). Acknowledge / Resolve / Dismiss are polling-safe POST routes (`ai.intelligence.insights.*`) gated by the same permission + `throttle:ai.tool`, tenant-checked in the controller, and each transition is audited with before/after status and actor.

### Part 11 — Notifications & inbox

`AiInsightNotification` delivers a database notification on insight creation to every in-org user holding `ai.insights.view`. A global Inbox (`/notifications`) lists notifications, marks single (`notifications.read`) or all (`notifications.read-all`) as read, and 404s on foreign-owner rows. The navbar bell shows the real unread count + five latest; the sidebar has a Notifications item with a live unread badge.

### Part 12 — Scheduler & CLI

`AiGenerateInsights` command (`ai:generate-insights`, optional `--organization=`) runs `runForOrganizations()` (generate + expire + notify + audit) and is scheduled daily at `04:00` in `routes/console.php` behind loans/cash-flow/predictions.

### Part 13 — Security & tenant integrity

Detection uses builder predicates and service facades only — no raw SQL, no interpolation, no dynamic execution; cross-org action attempts return 403 with `security.unauthorized` audit; tool checks reject non-holder orgs; notifications guard `notifiable_type`/`notifiable_id`; a post-phase security scan found no new injection/unsafe-output vectors.

### Part 14 — Tests

`AiProactiveIntelligenceTest` (19 tests / 121 assertions) covers rule detection + severities, branch scoping, dedup/idempotency, dismissal immunity, reopen, lifecycle transitions with audits, domain expiration, predictive integration, role matrix, dashboard render/denial, cross-org 403, tool denial audit, formatter block, chat orchestration and notification delivery/read-all/404. `AiToolRegistryTest` asserts the 21-capability registry and the new capability surface. Fixture design keeps books balanced so only the rule under test fires, and Aged/ticket-constrained fixtures (schedule installment numbers, prediction `data_through`, journal `created_at`) are respected.

### Part 15 — Lint, regression & docs

`vendor/bin/pint` applied (import ordering, braces, whitespace); `php -l` green on all touched files; the new surface was re-tested green after formatting. Full regression re-run: AI feature directory per-file (accumulating `set_time_limit` budgets), `tests/Unit` (76), Finance (439 including a complete second run of the tail files), UserManagement + TenantIsolation + Security (108), Auth/Console/LandingPage/root-feature (95) and MemberPortal (234) — all green. This report plus `docs/phase-11.9-proactive-intelligence.md` document the phase.

---

**Outcome:** Phase 11.9 is fully implemented, tested, documented and regression-green, ready to commit. Next Phase 12 (ML/WhatsApp/voice/mobile) remains explicitly out of scope per the STOP rule.