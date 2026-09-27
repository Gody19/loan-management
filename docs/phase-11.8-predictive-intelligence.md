# Phase 11.8 — Predictive Intelligence (Deterministic Statistical Baselines)

**Start:** after Phase 11.7.1 commit `544fd14`
**Scope:** advisory "what happens next" outlooks for staff — portfolio forecast, cohort delinquency-risk indicator, cash-flow forecast and collection forecast — built from deterministic statistical baselines (moving average / naive / least-squares trend) over the organization's authoritative FinancePro records, persisted as idempotent, audited, tenant-scoped snapshots, surfaced on the Phase 11.7 Financial Intelligence dashboard and in the AI chat (orchestration), refreshed by a scheduled command.
**STOP rule honored:** no machine-learning models are trained or hosted (ML is a deferred extension point); predictions never feed a business rule (no loan approval/eligibility/rate/ledger/repayment decision reads them); no member-level detail is ever produced or exposed; the public landing-page assistant (Phase 11.7.1) can never reach this layer; no new auth layer, no breaking changes to existing AI security.

---

## 1. Status

**COMPLETE** — production surface, RBAC, orchestration, persistence, scheduler, tests and docs implemented; both test suites re-run twice (green).

---

## 2. Scope & design decisions

- **Organization-level, staff-facing.** Every insight is generated **per organization** (`scope = organization`). Branch narrowing is supported as an input filter but recorded in `assumptions` metadata, never as a separate row. Member/group-scope generation is explicitly deferred to future work.
- **Four domains**, each with one read-only capability:
  - `portfolio_forecast` — projected outstanding principal by month (§22),
  - `delinquency_risk` — a 0–100 cohort risk indicator (§24),
  - `cashflow_forecast` — projected net loan cash flow by month (§31),
  - `collection_forecast` — projected collections, due and overdue workload (§34).
- **Statistical only.** Methods: `naive`, `moving_average`, `linear_trend` (least squares). Confidence is derived from data depth (`minimum_history_periods` / `good_history_periods`) plus relative residual dispersion of the best-fit line (`high_dispersion_threshold`).
- **Complete-period rule.** Series are built **only from complete calendar months**; the in-progress month and any future-dated activity are never included in a series or in the newest-data freshness check.
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

- Migration `2026_09_26_120002_create_ai_predictions_table` → `ai_predictions` with unique `(organization_id, type, method, data_through)` and indexes on `type`, `organization_id`, `status`.
- `AiPrediction` model + enums (`PredictiveInsightType`, `PredictiveInsightStatus`, `PredictionConfidence`, `PredictiveDataQuality`) cast on the row; JSON `series`, `factors`, `assumptions`.
- `PredictiveIntelligenceService`:
  - `refresh()` — lazily regenerates only when a snapshot is absent, aged past `stale_after_days`, or the newest observable activity (posted repayment/disbursement, `<= now`) is newer than `data_through`;
  - `generate()` — idempotent `updateOrCreate` on the unique key, supersedes every other current row of the domain, and audits `ai.predictive.generated`;
  - `newestSourceDate()` / `forDashboard()` — tenant-aware freshness and per-org payloads for the dashboard.
- Old snapshots are marked `stale` then `superseded`; exactly one snapshot per (organization, domain) is current at a time.

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
- Dashboard `GET /ai/intelligence` (permission OR-list now includes `ai.predictive.view`) renders the "Predictive outlook (Advisory)" card with per-org snapshots and per-period series; the sidebar `$hasIntelligence` flag includes it.
- `Console\Commands\AiRefreshPredictions` (`ai:refresh-predictions`, optional `--organization=`) force-generates all four domains; scheduled daily at `03:00` in `routes/console.php`. `.env.example` gains the `PREDICTIVE_*` keys.

## 7. Test matrix (deterministic fixtures)

- **Unit** (`tests/Unit/PredictiveStatisticalForecastTest`): moving-average window semantics, trend slope, forecast continuation for each method, `methodFor` bounds and dispersion downgrade, residual dispersion.
- **Feature** (`tests/Feature/AI/AiPredictiveIntelligenceTest`): capability registered/read-only/not public; role grant matrix; denial for Secretary & VICOBA Member (with audit); chat orchestration; deterministic outputs — rising portfolio (`+100k/period → 1,500,000` cumulated), steady cash flow (`−40k/period → −120,000` cumulative net), collections (`60,000` projected, `1.3M / 13 loans` overdue trail), delinquency (`1/3 overdue, 30 dpd → 25.0, level low`); insufficient-data; idempotency; incomplete-period exclusion; future-dated activity ignored; tenant isolation; staleness/supersede via `CarbonImmutable::setTestNow` (reset in `finally`); dashboard render + denial; public-surface exclusion; registry count 20.
- Full suites run twice (`php -d max_execution_time=0 artisan test --testsuite=Feature|Unit`), then `vendor/bin/pint` and `php -l`.

## 8. Known limitations & deferred work

- Forecasts are point estimates from monthly buckets; no seasonality, no scenario bands, no ML. Model version and a drift marker are stored so a future ML model can coexist.
- Group-scope and per-branch snapshot rows are future work (branch filter is advisory metadata today).
- Disabled/AI-off environments still register the capability but the public surface and chat never surface it without the permission; `SUPER_ADMIN` seeding always includes it.