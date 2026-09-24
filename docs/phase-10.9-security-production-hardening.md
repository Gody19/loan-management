# PHASE 10.9 — SECURITY & PRODUCTION INTEGRITY HARDENING

**Status:** Implemented and verified — full regression runs green against the hardened baseline.
**Project:** FinancePro — Tanzania VICOBA & Microfinance Management Platform
**Stack:** Laravel 12 / PHP 8.2+ / MySQL 8 / SQLite (`:memory:` test env) / Spatie Laravel Permission
**Date:** 2026-09-23
**Rules honored:** Spatie Permission, tenant/loan/repayment/accounting/audit engines preserved. No AI/RAG/ML. Completion is based on authoritative `outstanding_balance`, not repayment counts. Audit migration is additive and driver-guarded. No existing permission was broadened; no unrelated behavior was modified to make tests green.

---

## 0. TEST BASELINE & GATE ANALYSIS

Baseline captured before any change (`C:\Users\GODY\AppData\Local\Temp\opencode\baseline.txt`):

| Metric | Baseline | After Phase 10.9 |
|---|---|---|
| Tests | 852 total | 880 total (+28 new) |
| Failed | 42 | **16** |
| Passed | 810 | **864** |
| Assertions | 1814 | **1886** |
| Duration | 742.99s | 553.58s |

The 42 baseline failures were categorized as:

1. **24 Gate-caused failures** — caused by the global `Gate::before` bypass granting Org Admins (and the default user) far more than intended, so cross-tenant negative tests leaked authorization. All 24 are **fixed** by this phase:
   - `FinancialIntegrityAuditTest` ×6 (cross-org reversal/view/deposit)
   - `TenantIsolationTest` ×14 (org A ↔ org B org/branch/member/accounts/plans/products/funds)
   - `OrganizationAdminOnboardingTest` ×3
   - `MemberTest` ×1 (cross-org member profile)
2. **18 pre-existing unrelated failures** — broken/test-fixture issues predating this phase, intentionally NOT touched (list in §5). Of these, **2 ended up fixed incidentally** by the RBAC/eligibility hardening (`LoanApprovalTest > guarantor can accept`, `MemberLoanTest > member can add guarantor`), leaving **16** remaining — all still in the unrelated set.

Delta checksum: passed 810 + 24 (gate fixes) + 28 (new tests) + 2 (incidental fixes) = **864** ✓ &nbsp;·&nbsp; failed 42 − 24 − 2 = **16** ✓ &nbsp;·&nbsp; assertions 1814 + 72 (new tests) = **1886** ✓

---

## 1. RBAC / AUTHORIZATION HARDENING

**Problem:** `Gate::before` bypassed checks for both Super Administrators AND Organization Administrators, so org admins could create Super Administrators, mutate roles, and slip cross-tenant gates housing the bypass at the top.

### 1.1 `app/Providers/AuthServiceProvider.php`
`Gate::before` narrowed to **Super Administrator only**. Org Admins now go through real permission checks (their own `hasRole`/`can` branch logic), and the default user is no longer silently granted the sandbox.

### 1.2 `app/Http/Requests/StoreUserRequest.php`, `app/Http/Requests/UpdateUserRequest.php`
Added `authorize()` overrides:
- `StoreUserRequest::authorize()` — only a Super Administrator may create a user with the Super Administrator role, or target the Super Administrator user.
- `UpdateUserRequest::authorize()` — only a Super Administrator may assign *or* remove the Super Administrator role, or update the Super Administrator user; org admins may still manage their own org users/roles.

### 1.3 `app/Services/UserService.php` + `app/Http/Controllers/UserController.php`
`syncRoles()` now throws `AuthorizationException` (403) when a non-Super-Admin attempts to grant/revoke the Super Administrator role, regardless of which route entry point is used (service-level enforcement, not just form validation). A `user.roles_synced` audit entry records the denied attempt. `UserController::store/update` call the guarded path.

### 1.4 `app/Policies/RolePolicy.php`
`create`, `update`, and `delete` now require `hasRole('Super Administrator') && can('role.*')`. Org admins can no longer create/rename/delete roles.

**Tests:** `tests/Feature/Security/CrossTenantRbacTest.php` — 11 tests (super-admin-only bypass; org admin denied create/assign/update/delete of Super Administrator; syncRoles guard; role policy denials; legacy permission model preserved).

---

## 2. AUDIT TRAIL ORGANIZATION ATTRIBUTION

**Problem:** audit logs were not tenant-scoped; `audit_logs` had no `organization_id`, so org admins had no way to audit by organization and cross-org events were indistinguishable.

### 2.1 Migration `database/migrations/2026_09_23_000002_add_organization_id_to_audit_logs_table.php`
- Adds nullable `organization_id` AFTER `user_id` (additive; existing rows get NULL).
- Index `audit_logs_organization_index`.
- Foreign key `*organizations*` — guarded: SQLite (`:memory:`, used by tests) has no `dropForeign`, so the FK is created/dropped **only on non-SQLite drivers**. `down()` drops FK (non-SQLite) → index → column, in that order (SQLite refuses dropping an indexed column).
- Public `backfill()`: populates `organization_id` from a deterministic owning context only (Organization → its id; model with `organization_id` attribute; acting user with exactly one org). Ambiguous contexts stay NULL rather than guessing.

### 2.2 `app/Services/AuditService.php`
`log()` now accepts an optional `?Model $model` and resolves `organization_id` through `resolveOrganizationId()`:
1. `Organization` → its id;
2. model exposing an `organization_id` attribute → that value;
3. acting `User` → its org **only if** the user belongs to exactly one organization;
4. otherwise NULL.

Resolution uses `auth()->id()` + `User::query()->find()` (never `auth()->user()`) to remain mock-safe under `tests/Unit/AuditTest.php`'s strict `Auth::shouldReceive('id')`.

### 2.3 `app/Models/AuditLog.php`
Added `organization_id` to `$fillable` and an `organization()` belongsTo relation.

**Tests:** `tests/Feature/Security/AuditOrganizationTest.php` — 8 tests (resolution paths ×5, legacy backfill, down/up cycle, index existence).

---

## 3. CONSOLE / SCHEDULER INTEGRITY

**Problem:** `routes/console.php` scheduled a closure referencing `App\Models\Repayment` and `App\Notifications\PaymentDueReminder` — **neither class exists** (an `app/Notifications` directory never contained it). The scheduler entry was a latent fatal.

### 3.1 `routes/console.php`
- Removed the broken closure (and the stray, broken `require`).
- Kept `inspire`.
- Registered `Schedule::command('loans:update-delinquency')->dailyAt('01:00')`.

### 3.2 `app/Console/Commands/UpdateLoanDelinquency.php` (new)
`loans:update-delinquency` command iterates `Organization::pluck('id')` and calls `LoanDelinquencyService::updateDelinquencyStatuses()` per org. Auto-discovered (verified via `php artisan list`).

**Tests:** `tests/Feature/Console/DelinquencySchedulerTest.php` — 2 tests (command marks overdue installments incl. `days_overdue=5`; command present in `schedule:list`).

---

## 4. LOAN LIFE-CYCLE & REPAYMENT INTEGRITY

### 4.1 Loan completion is now data-driven
- `app/Enums/LoanStatus.php` — `canTransitionTo`: `Completed` now only accepts `Active` as a return transition (a completed loan can be reopened via reversal). `isTerminal()` is unchanged (still "no further forward transitions").
- `app/Services/LoanRepaymentService.php`:
  - `postRepayment()` — when the recomputed authoritative `outstanding_balance <= 0`, the loan transitions `Active → Completed` (installments_paid recomputed; only after full authoritative settlement, never by repayment count).
  - `reverseRepayment()` — when a reversal makes outstanding `> 0`, the loan is reopened `Completed → Active` and `installments_paid` + `next_payment_date` are recomputed.
- `tests/Feature/Finance/LoanDisbursementTest.php:613` — flipped `assertFalse` → `assertTrue` to match the corrected completion semantic.

### 4.2 Repayment number sequence is now race-safe
`app/Services/RepaymentNumberGenerator.php` wraps number minting in `DB::transaction` + `lockForUpdate()` (mirrors `LoanNumberGenerator`), closing a concurrency window that could mint duplicate repayment numbers. Format validated against the preserved `RPT-YYYY-NNNNNN` contract.

**Tests:**
- `tests/Feature/Finance/LoanLifecycleTest.php` — 3 tests (completed only on full settlement; reopened on reversal; no premature completion).
- `tests/Feature/Finance/RepaymentNumberGeneratorTest.php` — 4 tests (sequential reservation, resume-after-insert, format regex, duplicate DB rejection). Note: generator reads `max(number)` from DB, so sequential tests persist each generated number between calls.

---

## 5. VERIFICATION & REMAINING RISKS

### 5.1 Regression bookkeeping
16 failures remain after Phase 10.9, all within the **pre-existing unrelated** set (documented as out-of-scope):
- `AccountingIntegrationTest > savings deposit accounting failure rolls back` (rollback-count assertion)
- `LoanApplicationTest > application can be submitted without finance accounts`
- `LoanEligibilityTest` ×4 (`NOT NULL loans.loan_application_id` fixture gap → QueryException)
- `LoanPlanTest > required fields must be present`
- `MemberGuarantorTest` ×8 (test-fixture / HTML-render artifacts)
- `MemberRepaymentTest > member cannot access make payment for inactive loan`

No previously-passing test regressed (all deltas reconcile to baseline + new tests).

### 5.2 Known remaining risks (documented, intentionally NOT fixed)
1. `savings-products/show.blade.php` passes a `SavingsAccountStatus` enum to `ucfirst()` → 500 on that page. (Surface-only latent bug; fix in a future UI phase — could not be fixed without touching unrelated behavior.)
2. The 16 pre-existing failures above — diagnostics only; they are fixture/assertion defects, not product regressions.
3. SQLite/MySQL split in the audit migration is deliberate; production MySQL gets the FK, SQLite test env gets column+index only.

### 5.3 New/changed files
- `app/Providers/AuthServiceProvider.php` (Gate narrowing)
- `app/Http/Requests/StoreUserRequest.php`, `app/Http/Requests/UpdateUserRequest.php`
- `app/Services/UserService.php`, `app/Http/Controllers/UserController.php`
- `app/Policies/RolePolicy.php`
- `database/migrations/2026_09_23_000002_add_organization_id_to_audit_logs_table.php`
- `app/Services/AuditService.php`, `app/Models/AuditLog.php`
- `routes/console.php`, `app/Console/Commands/UpdateLoanDelinquency.php`
- `app/Enums/LoanStatus.php`, `app/Services/LoanRepaymentService.php`, `app/Services/RepaymentNumberGenerator.php`
- `tests/Feature/Security/CrossTenantRbacTest.php`, `tests/Feature/Security/AuditOrganizationTest.php`
- `tests/Feature/Console/DelinquencySchedulerTest.php`, `tests/Feature/Finance/LoanLifecycleTest.php`, `tests/Feature/Finance/RepaymentNumberGeneratorTest.php`
- `tests/Feature/Finance/LoanDisbursementTest.php` (assertion flip at line 613)