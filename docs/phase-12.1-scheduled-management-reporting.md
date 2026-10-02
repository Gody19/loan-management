# Phase 12.1 — Scheduled Management Reporting & Executive Intelligence Digests

**Scope:** recurring delivery of the Phase 12.0 management reports — a closed set of frequencies targeting *completed* reporting periods — configured with tenant/branch scope and an audience, executed idempotently once per schedule and period, delivered as in-app notifications backed by a deterministic executive digest, and driven by a single scheduler path shared with the dashboard's "Run now" action.

**STOP rule honored:** scheduling is a read-only distribution mechanism. It never computes a figure, makes or recommends a decision, mutates a business record, or widens the authority of the user who created it. There is no user-supplied cron, no new business rule, no new prediction/insight engine, and no new inbox. Phase 13 was not started.

---

## 1. Status

**COMPLETE** — closed frequency vocabulary, completed-period engine extension, tenant/branch-scoped schedule lifecycle, deterministic forward-only next run, idempotent execution with per-recipient re-authorization, executive digest, scheduler command, dashboard surface, RBAC, and **42 feature tests (138 assertions)** all passing. Phase 12.0 regression (`AiIntelligenceReportingTest`, 53 tests / 631 assertions) re-run green. Combined: **95 tests / 769 assertions**.

---

## 2. Design principles

1. **Only completed periods are reported.** Every frequency maps to a settled Phase 12.0 period (`daily → yesterday`, `weekly → previous_week`, `monthly → previous_month`, `quarterly → previous_quarter`); a scheduled report can never present a partially elapsed window as a report. The runner re-asserts this normatively.
2. **Deterministic and strictly forward.** Frequencies are a closed vocabulary and the next run is computed in the schedule's own stored timezone, never from the server clock, and always moves forward — so a schedule can never be pinned to the past or silently drift if the application timezone changes.
3. **Configuration cannot widen access.** A schedule executes under a context *derived from* its owner and *narrowed* to the schedule's organization and branch. It can only remove access. If the owner has since lost the capability, organization or branch, the run fails rather than falling back to a broader authority.
4. **Exactly one execution per window.** A unique `(schedule, period)` execution key is claimed before any financial work happens, making the scheduler, a manual run and a racing worker idempotent by construction.
5. **Delivery is re-authorized per recipient.** Each recipient's own trusted context is rebuilt and asked whether it may read *this specific* report before a notification row exists.
6. **The digest is presentation, not computation.** It is assembled from the report's own classified dataset and introduces no new figure; the optional AI narrative is bounded and explicitly labelled advisory prose.

---

## 3. Frequency & period vocabulary

| Frequency | Completed period | Required fields |
| --- | --- | --- |
| `daily` | `yesterday` | time |
| `weekly` | `previous_week` | time, `weekday` (ISO 1–7) |
| `monthly` | `previous_month` | time, `day_of_month` (1–31) |
| `quarterly` | `previous_quarter` | time, `day_of_month` (1–31) |

`ReportPeriodType` gained `yesterday` and `previous_week` plus `isCompleted()`. `ReportPeriodService` resolves them through the same single Phase 12.0 engine, so comparison windows and `data_through` semantics are unchanged.

`ReportScheduleFrequency::nextRunAfter()` clamps a `day_of_month` beyond the target month's length (the 31st in February runs on the 28th/29th) using `addMonthsNoOverflow` — a plain `addMonths` would roll the 31st of January into March and silently skip February.

## 4. Persistence

| Table | Purpose |
| --- | --- |
| `ai_report_schedules` | configuration only: scope, name, report type, closed frequency, time/day, stored timezone, audience mode + recipients, narrative flag, active flag, next/last run, owner |
| `ai_report_schedule_runs` | one row per execution: unique `execution_key`, trigger, status, resolved period, timezone, recipient count, digest flag, safe failure reason, requester, timestamps |

`AiReportSchedule` casts report type/frequency/recipient mode to enums. `run_time` is deliberately a plain `H:i:s` string (a MySQL `time` column), never a datetime. `AiReportScheduleRun::executionKeyFor()` is the single deterministic definition of "the same run".

## 5. Lifecycle & next-run determinism

`AiReportScheduleService` owns create/update/enable-disable/delete, each audited. Branch narrowing is accepted only inside the acting user's trusted branch scope and the branch must belong to the schedule's own organization. Recipients are validated against currently authorized users of that organization and branch. Changing timing recomputes `next_run_at` strictly forward; disabling clears it; re-enabling recomputes from now so a schedule dormant for a month cannot fire a stale window.

## 6. Security model

- **Capability:** `ai.reports.schedule`, separate from the read-only `ai.reports.view` (a reader is not automatically allowed to subscribe an organization to automatic delivery). Management additionally requires `ai.reports.view`.
- **Grants:** Organization Administrator, Branch Manager, Auditor. Secretary and VICOBA Member are denied the whole surface.
- **Accounting schedules:** an accounting-intelligence schedule requires `ai.accounting.view` at creation, exactly as a manual generation does.
- **Tenant isolation:** a schedule outside the trusted scope is reported as not found (`BackedEnum`/`InvalidArgumentException` → redirect error), never disclosed. A foreign schedule id is denied.
- **Auditing:** `ai.report_schedule.created|updated|enabled|disabled|deleted|executed|failed|duplicate_suppressed|recipients_truncated`.

## 7. Execution runner

`AiReportScheduleRunner` is the only place a schedule produces anything:

- **Claim first.** A `running` run row is inserted under the unique execution key before any generation. A collision returns the existing run with a transient `replayed` marker — an idempotent skip, never a duplicate. A non-key unique violation is rethrown.
- **Narrowed context.** Generation runs through `AiIntelligenceReportService::generate(..., asOf: now(schedule timezone))` under a context derived from the owner and narrowed to the schedule's organization/branch.
- **Failure is isolated and durable.** A failed generation is stored as `failed` with a controlled (non-secret) diagnostic and never as a report; one broken schedule never aborts the pass. Each attempt advances the schedule exactly one period so failures retry once per period rather than spinning.
- **Advisory cache lock.** A short cache lock avoids duplicated work; the unique index remains the authoritative guard.

## 8. Delivery, digest & notifications

`AiScheduledReportNotification` is a database notification delivered through the existing global Inbox — no second inbox. `ReportScheduleDigestService` builds the headline and a deterministic summary from the report's own facts/advisories and data-quality notes, preserving the Phase 12.0 classifications verbatim (via `ReportDatumClassification::groupKey()`), and appends a bounded, explicitly labelled excerpt of the optional AI narrative. Each recipient is re-authorized with `AiIntelligenceReportService::findAuthorized()` before the notification is created. `max_recipients_per_run` caps delivery and the truncation is audited.

## 9. User surface

| Route | Purpose |
| --- | --- |
| `POST /ai/reports/schedules` | create a schedule |
| `PUT /ai/reports/schedules/{schedule}` | update a schedule |
| `POST /ai/reports/schedules/{schedule}/toggle` | enable/disable |
| `POST /ai/reports/schedules/{schedule}/run` | run now (same idempotent path) |
| `DELETE /ai/reports/schedules/{schedule}` | delete |

The reporting dashboard (`GET /ai/reports`) lists schedules and recent runs and hosts create/edit modals. Configuration lives in `config/intelligence-reporting.php` under `scheduling` with `AI_REPORT_SCHEDULES_*` keys in `.env.example`. `.env` was never modified.

## 10. Scheduler

`AiRunReportSchedules` (`ai:run-report-schedules`, optional `--organization=`, `--limit=`) is a thin wrapper over the runner and is registered in `routes/console.php` (`hourlyAt('05')->withoutOverlapping()`). A pass is idempotent per schedule and period, so a retried cron, a second worker or a manual invocation produces nothing new.

## 11. Verification

| Suite | Result |
| --- | --- |
| `AiScheduledReportingTest` (new) | **42 passed** (138 assertions) |
| `AiIntelligenceReportingTest` (Phase 12.0 regression) | **53 passed** (631 assertions) |
| Pint (all Phase 12.1 files) | clean |
| `php -l` (all touched files) | clean |
| `route:list --name=ai.reports` | 10 routes (5 new) |

## 12. Notes and limitations

- Reports are generated synchronously by the scheduler pass; no queue was introduced.
- Delivery is in-app (database) only; no email/SMS channel was added.
- The application timezone is `Africa/Dar_es_Salaam` and there is no organization timezone column, so each schedule stores an explicit, validated IANA timezone.
- `.env` was not modified; only `.env.example` documents the new keys.
- Phase 13 was not started.
