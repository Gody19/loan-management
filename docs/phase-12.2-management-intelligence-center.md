# Phase 12.2 — Unified Management Intelligence Center & Executive Review Workspace

**Scope:** a single read-only executive workspace that aggregates the already-built intelligence surfaces — Phase 11.7 descriptive intelligence, Phase 11.8 predictive outlooks, Phase 11.9 proactive insights, Phase 12.0 management reports and Phase 12.1 scheduled reports — into one consolidated, classification-preserving view with an optional AI explanation, scope/period filters and an attention queue.

**STOP rule honored:** the center *presents*, it does not compute or decide. It introduces no new prediction engine, insight engine, report engine, scheduler, notification system or classification enum. It never generates a report, prediction, insight, schedule run or business record on view, never resolves/dismisses an insight, never runs anomaly detection, and never influences a loan, rate, repayment or accounting rule. No WhatsApp/voice/mobile/member-facing surface, no arbitrary AI SQL, and Phase 13 was not started.

---

## 1. Status

**COMPLETE.** A new `ManagementIntelligenceCenterService` aggregation layer, a thin `ManagementIntelligenceCenterController`, one read-only route and view, and a sidebar entry. **22 feature tests (166 assertions)** pass, plus focused regression: `AiFinancialIntelligenceTest` (13/126), `AiAuthorizationTest` + `AiEndpointSecurityTest` (15/94) and a reporting/scheduling regression slice (11/39) — **61 tests / 425 assertions** run green this session.

---

## 2. Architecture

| Layer | Artifact | Responsibility |
| --- | --- | --- |
| Aggregation service | `app/AI/ManagementCenter/Services/ManagementIntelligenceCenterService.php` | Assemble one `overview` array from existing services/artifacts; classify every datum; capability-gate every section |
| HTTP controller | `app/Http/Controllers/ManagementIntelligenceCenterController.php` | Validate filter shape, resolve trusted context, re-validate branch, render; no computation, no AI call, no state change |
| Route | `routes/web.php` → `GET ai/intelligence-center`, name `ai.intelligence-center.index` | Read-only, gated by any one of ten intelligence/reporting capabilities |
| View | `resources/views/ai/intelligence-center/index.blade.php` | Presentation only; renders only sections the user holds |
| Nav | `resources/views/layouts/components/sidebar.blade.php` | "Intelligence Center" link, visible with any of the ten capabilities |

The service constructor receives only existing Phase 11.7 services (`Portfolio`, `Par`, `Delinquency`, `Collection`, `FinancialTrend`, `Accounting`), the Phase 12.0 `ReportPeriodService` and the Phase 12.0 `IntelligenceReportNarrativeService`. It does **not** depend on `PredictiveIntelligenceService` or `FinancialAnomalyDetectionService` so that a page view can never trigger recomputation or detection.

`overview()` returns: `period`, `period_types`, `branch_id`, `sections`, `executive_summary`, `predictions`, `attention_queue`, `reports`, `schedules`, `recent_runs`, `counts`, `capabilities`, `narrative`.

---

## 3. Dashboard sections

| Section | Source | Data |
| --- | --- | --- |
| Portfolio | `PortfolioIntelligenceService::summarize()` | active loans, outstanding, principal disbursed, 30/60-day maturities, plan composition |
| Portfolio at risk & delinquency | `ParIntelligenceService::summarize()` + `DelinquencyIntelligenceService::profile()` | PAR rate/buckets, delinquent principal and loan count |
| Collections | `CollectionIntelligenceService::summarize()` (period-bounded) | due, collected, collection rate, reversals |
| Cash flow | `FinancialTrendService::monthly()` | latest net movement (trend) + 12-month disbursed/collected facts |
| Accounting | `AccountingIntelligenceService::summarize()` | income, expenses, net, liquid position, trial-balance state, per-org breakdown |
| Operations | persisted `AiAnomalyFinding` (latest detection pass) + open `AiInsight` operational gaps | findings count, open operational advisories |
| Predictive outlook | persisted `AiPrediction` | latest snapshot per domain/org with status, quality, confidence, scope |
| Attention queue | persisted `AiInsight` | open insights, severity-ordered, existing lifecycle actions |
| Report library | persisted `AiIntelligenceReport` | classified reports with links to the existing detail page |
| Scheduled reports | persisted `AiReportSchedule` + `AiReportScheduleRun` | configuration and recent runs |

An executive summary mixes facts, trends, predictions and advisories, each bearing its explicit classification badge.

---

## 4. Classification contract

Every datum is built through `fact()` / `trend()` / `prediction()` / `advisory()` and carries `classification`, `classification_label` and `classification_color` from `ReportDatumClassification` (`fact → "Observed fact"`, `trend → "Trend"`, `prediction → "Prediction"`, `advisory → "Advisory"`). No classification enum was added; the contract is reused verbatim. A consumer can therefore never present an advisory or prediction as an observed fact.

---

## 5. Insights

The attention queue reads persisted Phase 11.9 `AiInsight` rows through the existing `forOrganizations()` / `open()` scopes and the existing lifecycle filters (severity, type, status). Actions reuse the existing `ai.intelligence.insights.acknowledge|resolve|dismiss` routes. **Viewing the center never marks an insight read and never changes its status** — there is no view audit and no read side effect.

---

## 6. Predictions

The predictive panel reads persisted Phase 11.8 `AiPrediction` snapshots directly (latest per domain and organization). It never calls `forDashboard()` or `refresh()`. Stale, superseded and poor-quality states are shown exactly as persisted. Snapshots are labelled "Prediction — not an actual recorded financial result", described as organization-scoped indications that are never branch-specific and never inputs to a business rule. The branch scope recorded in `assumptions.branch_ids` is surfaced as a label only.

---

## 7. Reports

The report library reads persisted Phase 12.0 `AiIntelligenceReport` rows, filters by type/status/period/generated-at, and links to the existing report detail page only when `ReportStatus::isReportable()`. A failed report is listed without a view link. Selecting from the library never regenerates a report.

## 8. Scheduled reports

The schedules block reads persisted Phase 12.1 `AiReportSchedule` and `AiReportScheduleRun` rows (no scheduler invocation). It is rendered only for users holding **both** `ai.reports.schedule` and `ai.reports.view`; a reader with only `ai.reports.view` sees neither the schedules nor the runs.

---

## 9. RBAC

The route accepts **any one** of ten capabilities (OR): `ai.portfolio.view`, `ai.delinquency.view`, `ai.collection.view`, `ai.trend.view`, `ai.accounting.view`, `ai.anomaly.view`, `ai.predictive.view`, `ai.insights.view`, `ai.reports.view`, `ai.reports.schedule`. Each section is additionally gated inside the service, so a user only sees sections they hold — the view never restates the mapping. The app's `CheckPermission` middleware is comma-separated OR, so the middleware list is comma-joined.

Secretary and VICOBA Member hold no management-intelligence capability and receive **403**; the center never reveals the existence or count of a section they cannot see. No role was granted a new capability.

---

## 10. Tenant & branch isolation

- The organization set always comes from the trusted `AiContextData`, never from the request. A foreign `organization_id` is never accepted.
- A requested `branch_id` is re-validated with `AiContextData::belongsToBranch()`. A foreign branch (another organization, or an unassigned branch in the same organization) is `abort(403, 'Unauthorized branch scope.')`.
- Only assigned branches are offered in the filter.
- Branch-scoped queries include organization-wide (`branch_id IS NULL`) rows plus the authorized branch ids; unauthorized-branch rows are hidden, never post-filtered for display.
- Accounting intelligence is organization-scoped by construction; when a branch filter is active the view states that the branch filter does not narrow it.

---

## 11. AI narrative

The AI explanation is **opt-in** (`?narrative=1`), generated by the existing Phase 12.0 `IntelligenceReportNarrativeService::generate()` over a reportData-shaped payload built from the center's classified sections. It is labelled "AI-generated advisory explanation". On provider failure or when AI is disabled, the service returns an unavailable reason and the center remains fully functional and unchanged. The AI cannot compute, query, change or override any figure, prediction, insight, report or business record.

---

## 12. Security & audit

The center performs **no state-changing action on view**: no report generation, no prediction/insight creation, no schedule run, no detection, no insight lifecycle mutation and no business-record write. There is no new notification and no view audit (viewing is intentionally quiet). State-changing insight actions continue to run through the existing audited Phase 11.9 routes. Tenant scope is derived server-side; the client can supply only validated filters.

---

## 13. Performance

- Descriptive sections delegate to the existing aggregate services once per request.
- Predictive reads are bounded (`orderByDesc('generated_at')->limit(200)`) before in-memory grouping.
- Attention queue capped at 50; report library at 25; schedules at 50; runs at 25; anomaly findings at 25.
- The optional narrative is the only external call and is off by default.
- Loading the center creates zero rows in `ai_intelligence_reports`, `ai_predictions`, `ai_insights`, `ai_report_schedules` and `ai_report_schedule_runs`.

---

## 14. Tests

`tests/Feature/AI/AiManagementIntelligenceCenterTest.php` — **22 tests / 166 assertions**:

- access: manager authorized, guest redirect, Secretary/VICOBA Member 403;
- isolation: foreign branch filter 403, unassigned same-org branch 403, foreign artifacts hidden, branch scope limits queue, only authorized branches offered;
- classification: every datum classified and all four types present, labels rendered;
- persisted/read-only: prediction shown without regeneration, viewing does not change insight status, no report/prediction/insight/schedule/run created on view, report library links, failed report has no link;
- capability gating: schedules only for scheduling holders, accounting section hidden without capability;
- AI: optional by default, generated on demand, graceful when unavailable;
- period: custom requires both boundaries, valid custom resolves.

Regression executed this session: `AiFinancialIntelligenceTest` 13/126; `AiAuthorizationTest` + `AiEndpointSecurityTest` 15/94; focused `AiIntelligenceReportingTest` + `AiScheduledReportingTest` slice 11/39.

---

## 15. Lint / regression / documentation

`vendor/bin/pint` clean on the new files and touched routes; `php -l` clean; `php artisan route:list --name=ai.intelligence-center` resolves one route. This document is the Phase 12.2 reference. No existing system was rebuilt; all Phase 11.7–12.1 behavior is reused as-is.
