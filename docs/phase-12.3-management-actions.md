# Phase 12.3 — Executive Action & Review Management

**Scope:** a controlled, human-only follow-up workflow layered over the intelligence surfaces. An authorized user can raise a management action from an advisory artifact (or standalone), assign it, start it, complete it and cancel it, with an immutable append-only timeline and audit trail. The Action Center and a read-only "Management follow-up" panel on the Intelligence Center present it.

**STOP rule honored:** this phase adds **no** prediction, anomaly, insight, report, scheduler, notification or AI-decision engine and no business-rule engine. **Management actions are human workflow records and do not execute financial or business decisions.** Nothing here auto-creates, auto-approves, auto-assigns, auto-completes, auto-escalates or auto-executes anything. No email, WhatsApp, SMS, voice or mobile delivery was added — assignment reuses the existing in-app database notification infrastructure only. AI is advisory; a named human is responsible for every action.

---

## 1. Status

**COMPLETE.** New enums, migrations, models, a workflow DTO, three services, a controller, an in-app notification, three views, one route group and seeded capabilities. **24 feature tests (80 assertions)** pass. Regression run green this session: `AiManagementIntelligenceCenterTest` (22/166), `AiAuthorizationTest` + `AiEndpointSecurityTest` (15/94), `AiScheduledReportingTest` (42/138), `AiIntelligenceReportingTest` (53/631), `AiProactiveIntelligenceTest` + `AiPredictiveIntelligenceTest` (44/259). Phase 13 was not started.

---

## 2. Architecture

| Layer | Artifact | Responsibility |
| --- | --- | --- |
| Enums | `app/Enums/ManagementActionStatus.php`, `ManagementActionPriority.php` | Curated status/priority vocabularies and transition rules |
| Persistence | `database/migrations/2026_10_02_000001_create_management_actions_table.php`, `..._000002_create_management_action_events_table.php` | Action rows + append-only timeline |
| Models | `app/Models/ManagementAction.php`, `ManagementActionEvent.php` | Casts, relations, scopes, due-state presentation |
| DTO | `app/AI/ManagementActions/Data/ManagementActionData.php` | Read-only workflow classification (`workflow` / "Workflow action") |
| Authorization | `app/AI/ManagementActions/Services/ManagementActionAuthorizationService.php` | Capability checks, tenant/branch scope, assignee resolution |
| Queries | `app/AI/ManagementActions/Services/ManagementActionQueryService.php` | Filtered list + dashboard counters (read-only) |
| Lifecycle | `app/AI/ManagementActions/Services/ManagementActionService.php` | create / update / assign / start / complete / cancel, audited + timeline |
| HTTP | `app/Http/Controllers/ManagementActionController.php` | Validate request shape, resolve trusted context, delegate |
| Routes | `routes/web.php` → `ai/actions` group, name `ai.actions.*` | Nine capability-gated routes |
| Views | `resources/views/ai/intelligence-center/actions/{index,create,show}.blade.php` | Action Center UI |
| Notification | `app/Notifications/ManagementActionAssignedNotification.php` | Database-channel assignment notice |
| Config | `config/intelligence-actions.php` | `due_soon_days`, `index_per_page`, `recent_completed_limit` |

The controller performs no tenant decision and no lifecycle rule of its own; both live in the services so a foreign id is not-found/forbidden rather than a disclosure.

---

## 3. Domain model

A `ManagementAction` holds no money column and never touches a business record. Columns: organization/branch scope, `created_by`, `assigned_to`, title, description, priority, status, `due_date`, the lifecycle timestamps `started_at`/`completed_at`/`cancelled_at`, `source_type`/`source_id`/`source_label`, `completion_notes`, `cancellation_reason`. Composite indexes cover `(organization_id, status, due_date)`, `(organization_id, assigned_to, status)` and `(source_type, source_id)`.

`ManagementActionEvent` is append-only (`UPDATED_AT = null`): action id, denormalized organization id, actor, event name, old/new status, old/new assignee ids (no FK, historical), notes, JSON metadata and `created_at`. Rows are never updated or deleted by the application.

---

## 4. Lifecycle & transitions

`open → in_progress → completed` and `open|in_progress → cancelled`. `completed` and `cancelled` are **terminal and immutable** — no reopen, no reassignment, no edit. `ManagementActionStatus::canTransitionTo()` is the single source of truth; an invalid move throws `InvalidArgumentException` and the controller returns with an error, leaving state untouched. Every transition appends a timeline event and an audit log. Status is never changed by a due date passing — overdue is a presentation state only.

---

## 5. Priority & due state

Priority (`low`, `medium`, `high`, `urgent`) is always chosen by a human and never derived from an AI insight or severity, so an advisory can never promote itself into an urgent task. `dueState()` is purely descriptive (`overdue`, `due_today`, `due_soon`, `upcoming`, `no_due_date`, or terminal) and drives labels/colors; `due_soon_days` defaults to 3.

---

## 6. Assignment

Assignment is a separate, separately-authorized act. A user may always self-assign; assigning somebody else requires `ai.actions.assign`. The assignee must already hold `ai.actions.view` and be inside the action's organization (and branch, when the action is branch-scoped), so an action can never be assigned to somebody who cannot read it. Re-assigning to the same person is a no-op. Assignment notifies the assignee only when the assignee is not the actor.

---

## 7. Source provenance

An action may optionally be raised from an `AiInsight`, `AiPrediction`, `AiIntelligenceReport` or `AiAnomalyFinding`. The request supplies a stable alias (`insight`/`prediction`/`report`/`anomaly`); the stored `source_type` is the fully-qualified class and `source_label` is a snapshot so the action stays readable if the source is later deleted. The source must exist and belong to the acting organization, so an action can never point at another tenant's artifact. The source is a provenance link only — it is never mutated.

---

## 8. RBAC

Three deliberately coarse capabilities avoid permission proliferation:

| Capability | Grants |
| --- | --- |
| `ai.actions.view` | Read the Action Center, an action and its timeline |
| `ai.actions.manage` | Create, edit, start, complete and cancel |
| `ai.actions.assign` | Assign an action to another user |

Grant matrix seeded by `RolePermissionSeeder`: Organization Administrator and Branch Manager hold all three; Loan/Credit/Collection Officer, Treasurer and Accountant hold `view` + `manage`; Auditor holds `view` only (oversight without action authority); Secretary and VICOBA Member hold none; Super Administrator receives all (sync). Route middleware is comma-OR via the app's `CheckPermission`, each route also re-authorized inside the service.

---

## 9. Tenant & branch isolation

- The organization always comes from the trusted `AiContextData`; a request can never supply it.
- A branch-limited user (has branch assignments) may only see and act on actions inside those branches and **must** select a branch when creating (org-wide creation is denied). An org-wide user (no branch assignments) reaches every branch and organization-wide actions.
- `applyScope()` constrains every query to the actor's organizations first, then to their branches; `assertWithinScope()` re-checks before any mutation.
- A foreign or out-of-scope action is a 404 / not-found, never a disclosure.

---

## 10. Audit & timeline

Every state change writes to the existing `AuditService` (`ai.management_action.created|updated|assigned|started|completed|cancelled`) and appends an ordered `ManagementActionEvent`. The show view renders the timeline; the events table is append-only and ordered by id. Assignment records the previous and new assignee.

---

## 11. Intelligence Center integration

`ManagementIntelligenceCenterService` receives the `ManagementActionQueryService` and adds a read-only `management_follow_up` key (`available`, counters `open`/`in_progress`/`overdue`/`due_soon`/`assigned_to_me`/`unassigned`, and `recently_completed`) plus `capabilities['actions']`. The center view renders a "Management follow-up" panel only for holders of `ai.actions.view`. Workflow data is deliberately **not** merged into the four-classification summary/sections — an action is not a fact, trend, prediction or advisory. Loading the center creates no action and appends no event.

---

## 12. Notifications

`ManagementActionAssignedNotification` uses the database channel only and reuses the existing personal inbox. Its payload states that a task was assigned and links to the action; it carries no financial instruction and no AI conclusion. No new delivery channel was introduced.

---

## 13. Security & STOP-rule compliance

Human-only transitions, no privilege escalation, terminal immutability, tenant/branch re-validation from trusted context and capability checks on every route and service call. The AI never creates, assigns, starts, completes or cancels an action; there is no automatic scheduling or escalation. Management actions are human workflow records and do not execute financial or business decisions. No financial record, ledger entry, loan, repayment or eligibility decision is read for mutation or altered.

---

## 14. Tests

`tests/Feature/AI/ManagementActionTest.php` — **24 tests / 80 assertions**:

- access: viewer authorized, guest redirect, Secretary/VICOBA Member 403, Auditor view-but-not-manage/assign, manage-only officer cannot assign;
- creation: manager creates, created event + audit trail recorded, branch-limited must choose a branch, cannot create in an unassigned branch, can create in own branch;
- isolation: foreign action not viewable, branch scope limits the list, org-wide action invisible to a branch-limited user but visible org-wide;
- lifecycle: ordered append-only timeline (`created → started → completed`), completed immutable, invalid transition rejected, cancel terminal;
- assignment: notifies the assignee, cannot assign to a user without read access;
- provenance: authorized source stored as FQCN + label, foreign source rejected;
- dashboard: overdue counted;
- center: follow-up panel shown and no writes on view, panel hidden without the view capability.

Regression executed this session: center 22/166, authorization + endpoint security 15/94, scheduled reporting 42/138, intelligence reporting 53/631, proactive + predictive 44/259.

---

## 15. Lint / regression / documentation

`vendor/bin/pint --test` clean on all new and touched files; `php -l` clean; `php artisan route:list --path=ai/actions` resolves nine routes. This document is the Phase 12.3 reference. No existing system was rebuilt; all Phase 11.7–12.2 behavior is reused as-is.
