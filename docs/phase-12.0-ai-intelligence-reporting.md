# Phase 12.0 — AI Intelligence Reporting & Executive Insights

**Scope:** deterministic, tenant-scoped management reporting for FinancePro — six report types across a controlled set of periods — persisted as auditable artifacts, rendered on a dedicated dashboard with print and CSV export, exposed to the AI as a read-only tool with no persistence, and explained by an optional AI narrative layer that can never alter a figure.

**STOP rule honored:** the AI never computes, decides, approves, or mutates. Every figure is produced by existing authoritative FinancePro services; the AI only explains data that already exists. No new business rules, no new prediction engine, no new auth layer, and no breaking changes to existing AI security.

---

## 1. Status

**COMPLETE** — six report types, period engine, classification contract, persistence, RBAC, orchestration, tool boundary, optional narrative, dashboard/print/CSV UI, and 53 feature tests (631 assertions) all passing. Phase 11 regressions re-run green: 55 AI/security/orchestration/registry tests, 31 accounting tests.

---

## 2. Design principles

1. **The AI never computes.** Report data comes only from existing authoritative services (`PortfolioPerformanceService`, `LoanPerformanceService`, `CollectionIntelligenceService`, `DelinquencyIntelligenceService`, `MemberService`, `SavingsService`, `TrialBalanceService`, `IncomeStatementService`, `BalanceSheetService`, `CashBankLedgerService`, `PredictiveIntelligenceService`, `ProactiveInsightService`). No report section recalculates a balance independently.
2. **Everything is classified.** Every single datum is exactly one of `fact`, `trend`, `prediction`, or `advisory`, and that label is rendered, exported, and given to the provider. A prediction can never be read as a fact.
3. **Absence is disclosed, never invented.** A missing previous period yields `direction = unavailable` with null change values plus an explicit data-quality note — not a fabricated `0%`.
4. **The narrative is optional and disposable.** Provider failure degrades to `available: false` with a reason; the report itself is never affected.
5. **Tenant scope is structural.** Organization, branch, and permissions come from `AiContextBuilderService`/`AiContextData` and `AiToolPolicy`; they are never accepted as free arguments.

---

## 3. Report types

| Type | Key | Content |
| --- | --- | --- |
| Executive portfolio | `executive_portfolio` | members, loans, outstanding principal, disbursements, maturities, PAR, delinquency, concentration, collections, savings/cash, predictive + proactive |
| Loan performance | `loan_performance` | portfolio distribution, status, plan mix, arrears, maturity, predictive + proactive |
| Collections | `collections` | due, collected, collection rate, arrears, reversals, collection trend |
| Cash-flow intelligence | `cashflow_intelligence` | ledger opening/inflows/outflows/closing per configured account, loan-cycle flows, predictive + proactive |
| Accounting intelligence | `accounting_intelligence` | trial balance, income statement, balance sheet, draft journals, unbalanced condition |
| Operational intelligence | `operational_intelligence` | delinquency, anomalies, concentration, data quality |

`accounting_intelligence` additionally requires `ai.accounting.view` — the reporting capability never widens access to financial statements.

## 4. Period engine

`ReportPeriodService` resolves `today`, `this_week`, `this_month`, `this_quarter`, `this_year`, `previous_month`, `previous_quarter`, and `custom`.

- Every period carries its own `previousStart`/`previousEnd`, so comparison windows are deterministic rather than "the same period last year".
- `custom` requires an explicit ordered range and is rejected when it exceeds `max_custom_range_days` (default 366).
- `period_end`, `data_through` (newest authoritative record observed) and `generated_at` (render time) are three distinct fields and are never conflated.
- An unknown period is a validation error, never a silent fallback to "this month".

## 5. Classification contract

`ReportDatum` carries `classification`, `classification_label`, `source`, and `meta`, plus trend-specific `previous_value`, `absolute_change`, `percentage_change`, `direction`, `direction_label`.

- A trend over a previous value of `0` reports `unavailable`, never `INF` or `0%`.
- Predictions carry their own status, confidence, and data-quality labels from Phase 11.8 unchanged.
- Advisories carry the Phase 11.9 severity, type, status, recommendation, and a lifecycle statement making explicit that acting on an insight is a human decision.

Report-level `data_quality` is the honest union of every limitation any section declared, so an unavailable prediction or comparison cannot be missed by a reader who only scans the summary.

## 6. Persistence

`ai_intelligence_reports` stores the full deterministic dataset, the optional narrative, the resolved period and comparison window, `data_through`, `generated_at`, the requester, and the scope.

- Status is `generating` → `completed` | `failed`. **Only `completed` reports are viewable, printable, exportable, or injectable into AI.**
- A failed generation records the failure against its row and rethrows a generic `InvalidArgumentException`; the raw internal cause never reaches the caller.
- Reports are analytical artifacts of a past moment: `User::reports()` is deliberately separate from any live dashboard relationship.

## 7. Security model

- **Capability:** `ai.reports.view`, registered with a tenant-free argument schema (`report_type`, `period`, `from`, `to`).
- **Grants:** Organization Administrator through Auditor. **Secretary and VICOBA Member are denied.**
- **Tenant isolation:** the organization is resolved from the trusted context; a foreign report id is a `404`, never a disclosure. A branch must match both the trusted organization *and* the trusted branch assignment.
- **Tool boundary:** the AI tool runs `persist: false` and `withNarrative: false`, so conversational use cannot flood the report table or incur provider cost.
- **Forbidden arguments:** `AiToolPolicy` blocks `organization_id`, `branch_id`, `member_id` and privilege-bearing keys at the tool boundary.
- **Auditing:** `ai.report.generated`, `ai.report.failed`, `ai.report.viewed`, `ai.report.printed`, `ai.report.exported`.

### Branch scope and the ledger

Ledger lines carry no `branch_id`; the branch lives on `journal_entries`. A branch-scoped accounting report therefore filters `journal_entries.branch_id`.

`TrialBalanceService`, `IncomeStatementService`, `BalanceSheetService` and `CashBankLedgerService` gained an optional trailing `?int $branchId = null`. Omitting it preserves the exact organization-wide behaviour every existing caller relies on — all 31 Phase 8 accounting tests pass unmodified. Without this, a branch-scoped report would have shown organization-wide figures under a branch heading: a mislabelled number rather than a wrong one.

## 8. AI narrative layer

`IntelligenceReportNarrativeService` is optional (`narrative.enabled`), runs *after* the deterministic report exists, and receives only a bounded, sanitized rendering of the classified dataset — never a data dump, never a credential, never a member detail.

- Provider exceptions are caught and reported as unavailable with a safe reason.
- The system guidance forbids inventing, recomputing, contradicting, or filling in absent figures, and forbids any statement that an action was required, approved, or taken.
- The dashboard distinguishes an available narrative from an unavailable one, so the absence is visible rather than silent.

## 9. User surface

| Route | Purpose |
| --- | --- |
| `GET /ai/reports` | dashboard: generation form + own recent reports |
| `POST /ai/reports` | generate (validated type, period, custom range, branch) |
| `GET /ai/reports/{report}` | completed-only detail with classification badges |
| `GET /ai/reports/{report}/print` | self-contained printable document |
| `GET /ai/reports/{report}/export` | classified CSV, audited |

Configuration lives in `config/intelligence-reporting.php` with `INTELLIGENCE_REPORTING_*` keys in `.env.example`. `.env` was never modified.

---

## 10. Verification

| Suite | Result |
| --- | --- |
| `AiIntelligenceReportingTest` | **53 passed** (631 assertions) |
| `AiToolRegistryTest` | 10 passed (291 assertions) |
| `AiChatOrchestrationTest` + `AiToolEndpointTest` + `AiAuthorizationTest` + `AiContextTest` + `AiEndpointSecurityTest` | 45 passed (276 assertions) |
| `AccountingTest` (Phase 8, unmodified callers) | 31 passed (65 assertions) |
| Pint (all Phase 12 files) | clean |

`php artisan route:list --path=ai/reports` confirms all five routes. `php -l` is clean on every touched file.

## 11. Notes and limitations

- Reports are generated synchronously on request; no queue was introduced.
- The narrative reuses the existing provider abstraction. If no provider is configured, every report still works and simply reports no narrative.
- Phase 11.8 snapshots are organization-scoped; a branch-scoped report inherits that scope for predictive figures. This is surfaced rather than silently mixed.
- Phase 13 was not started.