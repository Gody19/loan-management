# Phase 11.8 — Predictive Intelligence (Deterministic Statistical Baselines)

**Start:** after Phase 11.7.1 commit `544fd14`
**Scope:** advisory "what happens next" outlooks for staff — portfolio forecast, cohort delinquency-risk indicator, cash-flow forecast and collection forecast — built from deterministic statistical baselines (moving average / naive / least-squares trend) over the organization's authoritative FinancePro records, persisted as idempotent, audited, tenant-scoped snapshots, surfaced on the Phase 11.7 Financial Intelligence dashboard and in the AI chat (orchestration), refreshed by a scheduled command.
**STOP rule honored:** no machine-learning models are trained or hosted (ML is a deferred extension point); predictions never feed a business rule (no loan approval/eligibility/rate/ledger/repayment decision reads them); no member-level detail is ever produced or exposed; the public landing-page assistant (Phase 11.7.1) can never reach this layer; no new auth layer, no breaking changes to existing AI security.

---

## 1. Status

**COMPLETE** — production surface, RBAC, orchestration, persistence, scheduler, tests and docs implemented; both test suites re-run twice (green). Spec gap-closers shipped in a follow-up commit: explicit `data_from` window, `poor_quality_data` status with data-quality gate, manual refresh endpoint (with re-evaluation), branch-scope tracking in `assumptions`, model-version traceability, and request-level public-AI isolation.

---

## 2. Scope & design decisions

- **Organization-level, staff-facing.** Every insight is generated **per organization** (`scope = organization`). Branch narrowing is supported as an input filter, recorded in `assumptions.branch_ids`, and a snapshot is only ever reused when the requested branch scope matches the one that produced it (a narrower lookup never reuses an org-wide snapshot and vice versa). Member/group-scope generation is explicitly deferred to future work.
- **Four domains**, each with one read-only capability:
  - `portfolio_forecast` — projected outstanding principal by month (§22),
  - `delinquency_risk` — a 0–100 cohort risk indicator (§24),
  - `cashflow_forecast` — projected net loan cash flow by month (§31),
  - `collection_forecast` — projected collections, due and overdue workload (§34).
- **Statistical only.** Methods: `naive`, `moving_average`, `linear_trend` (least squares). Confidence is derived from data depth (`minimum_history_periods` / `good_history_periods`) plus relative residual dispersion of the best-fit line (`high_dispersion_threshold`).
- **Complete-period rule.** Series are built **only from complete calendar months**; the in-progress month and any future-dated activity are never included in a series or in the newest-data freshness check. Leading months with no activity are trimmed before statistics so a window that started recording mid-history is not read as a run of zero trend.
- **Explicit data window.** Every snapshot records `data_from` (start of the oldest complete month feeding it) alongside `data_through`; `generated_at` and freshness are surfaced in the tool payload and dashboard.
- **Advisory guarantee.** Snapshots live in `ai_predictions` purely for display/chat. No loan, repayment, schedule or ledger process reads them.

## 3. Configuration (`config/predictive-intelligence.php`, env `PREDICTIVE_*`)

| Key | Default | Meaning |
| --- | --- | --- |
| `model_version` | `statistical-baseline-v1` | version marker stored on every row |
| `history_months` | `12` | complete months evaluated per domain |
| `minimum_history_periods` | `3` | below this → `insufficient_data` snapshot |
| `good_history_periods` | `6` | at/above this → `good` quality |
| `default_horizon` / `max_horizon` | `3` / `6` | forecast months ahead |
| `moving_average_window` | `3` | trailing window for the MA method |
| `high_dispersion_threshold` | `0.35` | relative residual dispersion above which confidence drops |
| `delinquency_risk_levels.medium_threshold` / `.high_threshold` | `34` / `67` | risk-level buckets |
| `stale_after_days` | `7` | after this many days with no new data a snapshot is regenerated |
| `currency` | `TZS` | reported currency |

## 4. Persistence & lifecycle

- Migration `2026_09_26_120002_create_ai_predictions_table` → `ai_predictions` with unique `(organization_id, type, method, data_through)` and indexes on `type`, `organization_id`, `status`; follow-up migration `2026_09_26_140000_add_data_from_to_ai_predictions_table` adds nullable `data_from`.
- `AiPrediction` model + enums (`PredictiveInsightType`, `PredictiveInsightStatus` incl. `poor_quality_data`, `PredictionConfidence`, `PredictiveDataQuality`) cast on the row; JSON `series`, `factors`, `assumptions` (incl. `branch_ids` and `quality_gates`).
- `PredictiveIntelligenceService`:
  - `refresh()` — lazily regenerates only when a snapshot is absent, aged past `stale_after_days`, the newest observable activity (posted repayment/disbursement, `<= now`) is newer than `data_through`, or the requested branch scope differs from the snapshot's (`sameBranchScope`). A `$force` flag regenerates unconditionally (used by the manual refresh endpoint so the quality gate is re-evaluated);
  - `generate()` — idempotent `updateOrCreate` on the unique key (matching `data_through` via a Carbon value so the where clause matches the model's date serialization on sqlite and MySQL), supersedes every other current row of the domain, applies the data-quality gate, and audits `ai.predictive.generated` (incl. `data_from`, `data_quality_issue_count`);
  - `newestSourceDate()` / `forDashboard()` — tenant-aware freshness and per-org payloads for the dashboard.
- Old snapshots are marked `stale` then `superseded`; exactly one snapshot per (organization, domain) is current at a time. `poor_quality_data` snapshots count as current and are replaced when re-generated.
- **Data-quality gate.** `DataQualityService::issues()` detects, per organization (optionally per branch), duplicate posted repayments (same loan, date, amount), non-positive posted repayment amounts, loans missing a member, and schedules missing a loan. When `generate()` produced a `generated` baseline but issues exist, the snapshot is stored as `poor_quality_data` with the issues appended to `factors` and `assumptions.quality_gates`. The gate never edits source data and never blocks generation — it honestly labels the result.
- **Model-version traceability.** `model_version` is persisted per snapshot, so each row records exactly which baseline version produced it; older rows keep their version when superseded.

## 5. Data sources (authoritative operational tables)

- Portfolio: `Loan.disbursed_amount`, `disbursement_date` for `active`/`disbursed`/`completed` loans.
- Delinquency: recomputed at read time from `LoanRepaymentSchedule` (due date, outstanding, paid date) — never from any stored risk column.
- Cash flow: `LoanRepayment` (`status = posted`, `amount`) as inflows vs. disbursements as outflows.
- Collections: `LoanRepaymentSchedule.total_amount` as due, `LoanRepayment.amount` as collected, outstanding schedules due at/before month-end as overdue workload.
- Branch filters use `branch_id` (repayments/loans) or `whereHas('loan')` (schedules).

## 6. RBAC, orchestration, dashboard & scheduler

- Single permission **`ai.predictive.view`** granted by the seeder to Organization Administrator, Branch Manager, Loan Officer, Credit Officer, Collection Officer, Treasurer, Accountant, Auditor. **Not granted** to Secretary or VICOBA Member; Super Administrator receives everything.
- Registry: `ai.predictive.view` is a user-org-scope, zero-argument, read-only capability (last in the capability table, ordered after the Phase 11.7 anomaly entry). `AiPredictionResultFormatter` emits a `<FINANCEPRO_PREDICTION_DATUM>` block; `AiController::formatResult()` routes it (both `/ai/tool` and the orchestrated flow).
- Orchestrator keywords `forecast`, `predict`, `projection`, `outlook`, `what to expect` are **last** in `ORGANIZATION_INTELLIGENCE` so earlier Phase 11.7 matches win; label `predictive intelligence outlook`.
- Dashboard `GET /ai/intelligence` (permission OR-list now includes `ai.predictive.view`) renders the "Predictive outlook (Advisory)" card with per-org snapshots, the explicit data window (from → through), generated-at stamp and a **Refresh** button; the sidebar `$hasIntelligence` flag includes it.
- Manual refresh endpoint `POST /ai/intelligence/predictions/refresh` (`ai.intelligence.predictions.refresh`): gated by `ai.predictive.view`, throttled like the tool endpoints, tenant-scoped from the trusted context, force-regenerates all four domains (quality gate re-evaluated), records `generated_by`, and audits `ai.predictive.refreshed`.
- `Console\Commands\AiRefreshPredictions` (`ai:refresh-predictions`, optional `--organization=`) force-generates all four domains; scheduled daily at `03:00` in `routes/console.php`. `.env.example` gains the `PREDICTIVE_*` keys.

## 7. Test matrix (deterministic fixtures)

- **Unit** (`tests/Unit/PredictiveStatisticalForecastTest`): moving-average window semantics, trend slope, forecast continuation for each method, `methodFor` bounds and dispersion downgrade, residual dispersion.
- **Feature** (`tests/Feature/AI/AiPredictiveIntelligenceTest`): capability registered/read-only/not public; role grant matrix; denial for Secretary & VICOBA Member (with audit); chat orchestration; deterministic outputs — rising portfolio (`+100k/period → 1,500,000` cumulated), steady cash flow (`−40k/period → −120,000` cumulative net), collections (`60,000` projected, `1.3M / 13 loans` overdue trail), delinquency (`1/3 overdue, 30 dpd → 25.0, level low`); insufficient-data; idempotency; incomplete-period exclusion; future-dated activity ignored; tenant isolation; staleness/supersede via `CarbonImmutable::setTestNow` (reset in `finally`); dashboard render + denial; public-surface exclusion; registry count 20; **data window** (`data_from` = window start), **direct `generate()` idempotency**, **branch narrowing** (branch-A-only → `900,000` vs org-wide `1,800,000`, snapshot reuse blocked across scopes), **poor-quality gate** (duplicate repayments → `poor_quality_data` + `quality_gates` + audit count), **model-version traceability** (two snapshots, both preserved, each with its own version), **manual refresh** (holder ok + audit + `generated_by`, Secretary 403, cross-tenant no-op), **request-level public-AI isolation** (guest chat cannot reach the predictive layer).
- Full suites run twice (`php -d max_execution_time=0 artisan test --testsuite=Feature|Unit`), then `vendor/bin/pint` and `php -l`. The AI feature directory is run per-file because each `AiController` chat request calls `set_time_limit(600)`, whose budget accumulates across tests within one PHPUnit process.

## 8. Known limitations & deferred work

- Forecasts are point estimates from monthly buckets; no seasonality, no scenario bands, no ML. **Machine learning is deferred**: the available history is far too short (12 complete months per domain) for meaningful ML training, so deterministic statistical baselines remain the shipped method; `model_version` and drift markers make room for a future ML model to coexist.
- Group-scope and per-branch snapshot rows are future work (branch filter is advisory metadata today, but scope-aware reuse prevents a branch lookup from silently reusing an org-wide snapshot).
- The quality gate only re-evaluates when a snapshot is regenerated (new data, staleness, or manual refresh); fixing a duplicate source record does not auto-regenerate until one of those triggers fires.
- Disabled/AI-off environments still register the capability but the public surface and chat never surface it without the permission; `SUPER_ADMIN` seeding always includes it.