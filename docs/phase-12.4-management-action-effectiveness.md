# Phase 12.4 — Management Action Effectiveness & Executive Accountability

## 1. Status

Implemented. A **read-only measurement layer** over the Phase 12.3 management action workflow.

Phase 12.4 answers one question — *are follow-up actions being handled, completed, delayed and reviewed
effectively?* — and nothing else. It introduces no new business process, no new intelligence, no new
automation and no new permission.

Deliberately **not** built, per the STOP rules:

- no prediction, anomaly, insight, report, scheduler or notification engine;
- no automatic escalation, reassignment, closure or status change;
- no employee scoring, ranking or performance evaluation;
- no new capability (the existing `ai.actions.view` is reused);
- no migration, no new table, no change to the action schema.

## 2. Architecture

| Component | Path | Role |
| --- | --- | --- |
| Comparison DTO | `app/AI/ManagementActions/Data/ManagementActionComparison.php` | Null-safe period-over-period comparison |
| Measurement service | `app/AI/ManagementActions/Services/ManagementActionEffectivenessService.php` | All aggregation, aging, breakdowns, lifecycle |
| HTTP layer | `app/Http/Controllers/ManagementActionEffectivenessController.php` | Filter validation, trusted scope re-validation, rendering |
| Page | `resources/views/ai/intelligence-center/actions/effectiveness.blade.php` | Executive accountability dashboard |
| Center panel | `ManagementIntelligenceCenterService::managementFollowUp()` | Compact measurements on the Intelligence Center |
| Detail card | `resources/views/ai/intelligence-center/actions/show.blade.php` | Per-action lifecycle measurements |

Reused rather than rebuilt: `ManagementActionAuthorizationService` (scope),
`ManagementActionQueryService` (record list), `ReportPeriodService` (periods),
`ReportDatumClassification` (fact/trend labelling) and `config/intelligence-actions.php`
(`due_soon_days`).

`ManagementActionEffectivenessService` deliberately does **not** inject `ManagementActionService` — the
mutating service is structurally unreachable from the measurement layer, and a test asserts this by
reflection.

## 3. Metric definitions

All counts are measured over one **cohort**: actions whose `created_at` falls inside the selected
period. A separately labelled `live` block reports the current state of the entire authorized scope,
because an action raised last year and still open is outstanding today.

| Metric | Definition |
| --- | --- |
| Total actions | Actions created in the selected period |
| Open / In progress | Current status is `open` / `in_progress` |
| Unresolved | `open` + `in_progress` |
| Completed / Cancelled | Current status is `completed` / `cancelled` |
| Overdue | `due_date` before today **and** status unresolved |
| Due soon | `due_date` between today and today + `due_soon_days` **and** status unresolved |
| Unassigned | No assignee **and** status unresolved |
| Without due date | No `due_date` (can never be overdue or due soon) |
| Completion rate | `completed / total × 100` |
| Cancellation rate | `cancelled / total × 100` |
| Average completion time | Mean of `completed_at − created_at`, completed actions only |
| Average time to start | Mean of `started_at − created_at`, rows with `started_at` only |
| Aging | Unresolved cohort actions bucketed by age since `created_at` |
| Lifecycle (detail) | Milestones from `ManagementActionEvent`, falling back to action timestamps |

**Denominator decision.** Both rates use the whole cohort as the denominator rather than a
"resolvable" subset, so the status counts always reconcile with the total and the two rates are
comparable to each other.

**Honest-missing contract.** A rate with a zero denominator is `null`, never `0`. An average with no
qualifying row is `null`, never `0` and never NaN. Rows with a missing timestamp are excluded from an
average instead of being counted as zero elapsed time. The UI renders every such figure as `N/A`.

**Status filter semantics.** A `status` filter is applied only to the detailed record list, never to
the metrics, because filtering a "completed" count by status would make the metric self-referential.

## 4. Aging buckets

Fixed presentation contract, measured from `created_at` for unresolved actions:

`0-3`, `4-7`, `8-14`, `15-30`, `31+` days.

The buckets are intentionally **not** configuration: making them deployment-specific would let two
installations report different buckets for identical data. Each bucket is projected from absolute date
boundaries, and `31+` is a real predicate (`created_at < today − 30 days`) rather than a residual, so an
action can never be presented as 31+ days old when its age is merely unknown. Anything that cannot be
placed in a bucket at all (a null timestamp or future-dated clock skew) is reported separately as
`unclassified` and surfaced in the UI.

The sum of the buckets always equals the unresolved total.

## 5. Period comparison

`ReportPeriodService` supplies the current and previous windows. For total, completed, cancelled,
completion rate and cancellation rate the layer reports `current`, `previous`, `change` and `direction`.

- Count changes are absolute; the relative percentage is only offered when the previous count is non-zero.
- Rate changes are expressed in **percentage points**.
- `direction` is `up`, `down`, `flat` or `unavailable` — a movement is a measurement, never a verdict.
- When the previous period has nothing comparable, the comparison is explicitly `unavailable` with a
  stated reason rather than `0`. Growth from a zero baseline still reports an absolute change but no
  percentage, because dividing by zero has no honest answer.

Cohort figures are additionally exposed through `ReportDatumClassification` as **fact** (observed counts
and averages) and **trend** (period movement). Nothing is classified as a prediction or an advisory.

## 6. Distributions

- **Assignment** — neutral workload counts per assignee, with unassigned as its own row. Ordered by
  name, carrying **no score, rank or verdict**. A test asserts those keys never appear.
- **Branch** — neutral workload counts per branch; organization-wide actions (no branch) are reported as
  their own labelled row rather than hidden.
- **Source** — descriptive provenance across `insight`, `prediction`, `report`, `anomaly` and the
  explicit `standalone` value (actions raised without an intelligence source). All five categories are
  always present, including zero rows.

Breakdowns are bounded to 50 rows and always report when they were truncated, so a partial list can
never be mistaken for a complete one.

## 7. RBAC

Reuses `ai.actions.view`. A separate executive-reporting permission was deliberately **not** introduced,
to keep the capability matrix small and consistent with the STOP rules.

`ai.actions.manage` and `ai.actions.assign` are neither required nor sufficient — measurement is
read-only.

## 8. Tenant & branch isolation

Scope is applied in SQL through `ManagementActionAuthorizationService::applyScope()`; the request can
never widen it.

- A branch-limited user measures only their own branches.
- An organization-wide user measures their whole organization, including organization-wide actions.
- A requested branch must belong to one of the acting user's organizations and, for a branch-limited
  user, must be one of their assigned branches — otherwise `403`.
- A requested assignee must be a reader of the acting user's own organizations (all of them, not just
  the first) — otherwise `403`.
- Display names in breakdowns are resolved only from within the acting user's organizations, with
  qualified columns so the pivot join cannot leak a foreign user.

An empty branch filter means "my whole authorized scope" and is never rejected: unlike the Phase 12.3
create flow — where no branch means an organization-wide action a branch-limited user may not create —
a read filter must always be able to express everything the user can see.

## 9. Performance

Every figure is aggregate SQL (`COUNT` / `SUM` / `AVG` / `GROUP BY`) over indexed columns; the action
and event tables are never loaded into memory to be counted. Each projection is built from a clone of
the cohort builder, so an average can never narrow the cohort that the aging and breakdown projections
are measured from. Elapsed-time averages are computed in SQL with a dialect-aware expression
(`strftime` on SQLite, `TIMESTAMPDIFF` elsewhere) so one definition works on both the MySQL production
environment and the SQLite test environment.

## 10. Read-only guarantee

The layer cannot mutate the workflow:

- no create, assign, start, edit, complete, cancel or escalate path exists in it;
- the mutating `ManagementActionService` is not a dependency (reflection-tested);
- loading the dashboard, the Intelligence Center panel or an action detail page writes no action row,
  no timeline event and no `updated_at` change (asserted in tests).

## 11. Intelligence Center integration

The existing "Management Follow-up" panel is extended, not replaced: the Phase 12.3 live counters are
untouched and an "Action effectiveness" block adds total, unresolved, completion rate, cancellation rate,
average completion time and average time to start, with a link to the full dashboard. The block is
hidden entirely for a user without `ai.actions.view`.

## 12. Action detail integration

The action detail page gains a "Lifecycle measurements" card: created / started / completed / cancelled
milestones plus time to start, time to complete and total age, with the total age explicitly labelled as
measured either up to the recorded closure or up to now while the action is still open.

Milestones come from the append-only `ManagementActionEvent` timeline and fall back to the action's own
timestamps only when no matching event exists. Unmeasurable values are shown as `N/A`.

## 13. Security & STOP-rule compliance

- No AI provider is invoked. The layer is deterministic; nothing is generated, inferred or advised.
- No write path, no escalation, no automation, no ranking of people.
- No financial data is read, moved or changed; the layer touches only action workflow columns.
- Every query is tenant- and branch-scoped, and all request-supplied scope ids are re-validated against
  the trusted context before use.

## 14. Tests

`tests/Feature/AI/ManagementActionEffectivenessTest.php` — 41 tests covering:

access (viewer / auditor / unauthorised role / guest), counts and shared denominators, the honest
null contract, overdue and due-soon definitions, terminal actions never counting as unassigned,
completed-only and started-only timing averages, aging classification and exact reconciliation,
unclassified aging rows, assignment and branch distributions including the organization-wide row,
branch-limited isolation, source distribution including `standalone`, metric/record-list agreement for
the `standalone` filter, period comparison up/down/flat/unavailable and zero-baseline behaviour, custom
period windows, the read-only guarantee, the reflection-based no-mutating-dependency guarantee, workflow
reflection after a real start/complete cycle, cross-tenant isolation, foreign branch and assignee
rejection, organization-wide branch filtering, validation rejection, page labelling, the bounded record
list, the Intelligence Center integration and the per-action lifecycle card.

## 15. Lint / regression / documentation

- `php -l` clean on every new and modified PHP file.
- `vendor/bin/pint --test` clean on every new and modified PHP file.
- Regression suites re-run after the change (see the Phase 12.4 delivery report).
- Documentation: this file.
