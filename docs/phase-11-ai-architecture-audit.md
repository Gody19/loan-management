# PHASE 11.0 — VICOBA AI ARCHITECTURE AUDIT

**Status:** Architecture audit and design only — NO application code was modified.
**Project:** FinancePro — Tanzania VICOBA & Microfinance Management Platform
**Stack audited:** Laravel 12 / PHP 8.2+ / MySQL 8 / Blade / Bootstrap 5 / Tailwind v4 / Vanilla JS / Spatie Laravel Permission
**Audit date:** 2026-09-23
**Rule honored:** The AI module is an *intelligence layer* on top of the existing platform. No Finance, Loan, Accounting, Guarantor, Collateral, RBAC, Audit, or tenure logic is rebuilt or bypassed.

---

## 0. AUDIT METHOD

Read-only inspection was performed across:

- `bootstrap/app.php`, `routes/web.php`, `routes/console.php`
- All 27 singleton-domain models under `app/Models/`
- All 50 services under `app/Services/`
- All 27 policies + `app/Providers/AuthServiceProvider.php`
- All middleware, `config/` (permission, auth, database, cache, queue, filesystems)
- 6 seeders, 50 migrations, 27 factories
- 43 test files (authorization, tenant isolation, member portal, finance, accounting, audit)
- Both layouts + both sidebars/navbars, `dashboard/index.blade.php`, member portal views, package.json / vite config
- `.env` and `phpunit.xml`

No files under `app/`, `routes/`, `resources/`, `database/`, or `tests/` were changed.

---

## A. EXISTING ARCHITECTURE SUMMARY (what actually exists)

### A.1 Routing & HTTP layer

- **Single entry point** `routes/web.php` (516 lines). **No `routes/api.php`** exists. All traffic is session/Blade web traffic via a single `web` guard.
- Middleware aliases registered in `bootstrap/app.php:19-24`: `role`, `permission`, `suspended`, `member`.
- `CheckSuspended` is appended to the whole `web` group. `HandleAuthorizationFailure` converts 403 → JSON or `redirect()->route('dashboard')` with flash.
- **No queueable jobs exist.** `QUEUE_CONNECTION=database` is configured in `.env`, `composer` dev script runs `queue:listen`, but the app currently has zero `app/Jobs`. Any future async AI work must establish the job infrastructure first.
- `routes/console.php` registers one daily scheduled closure that references `App\Models\Repayment` and `App\Notifications\PaymentDueReminder` — **neither class exists** (`app/Notifications` is empty). The scheduler entry is a latent fatal: it will throw when the scheduler runs. (See Risks §L.)

### A.2 Authentication

- `config/auth.php`: single session guard `web`, Eloquent `App\Models\User` provider, default guard `web`.
- `User` uses Spatie `HasRoles` + `HasFactory` + `Notifiable`. Tenant links are pivot relations `organizations()` (`organization_user`) and `branches()` (`branch_user`).
- **Member ↔ user binding is strict:** `User::member()` is `hasOne(Member::class)`. The `member` middleware (`EnsureMemberAssociated`) requires `$user->member` to exist AND `membership_status === 'active'`, else 403. This is the single point of "who is the current member" and is reused directly by the member resolver for AI.
- Login redirect is role-aware: a user with an active `Member` record goes to `member.dashboard`, otherwise `dashboard`.

### A.3 Authorization / RBAC (Spatie, permission-driven)

**Roles (11)** — defined in `database/seeders/RolePermissionSeeder.php`:

| Role | Identity check | Grants |
|---|---|---|
| Super Administrator | `Gate::before` global bypass + `hasRole(...)` in every policy/service | All 341 permissions (`module.action`) |
| Organization Administrator | `Gate::before` global bypass + tiered `hasRole` branches | All permissions except `audit.*`; includes user/role/settings |
| Branch Manager | `hasRole` branches + `branch_user` pivot | Module whitelist: dashboard,branch,group,member,savings*,shares*,welfare*,loan_plan,loan_eligibility,loan_application,loans,loan-repayments,meetings,reports |
| Loan Officer | Permission-only (`can(...)`) | 17 explicit permissions (view/create/update loans & applications, repayments) |
| Credit Officer | Permission-only | Loan Officer set + `loans.approve` |
| Treasurer | Permission-only | savings/shares/welfare/payment/accounting/reports |
| Accountant | Permission-only | dashboard/accounting/reports |
| Secretary | Permission-only | dashboard/member/group/meetings/reports |
| Collection Officer | Permission-only | dashboard.view, member.view, savings.view/create, loans.view, loan-repayments.view/create, reports.view |
| Auditor | Permission-only | dashboard/reports/audit.View-only audit permissions (no `audit.create/...` exist) |
| VICOBA Member | `member` middleware | Read-only: dashboard.view, savings.view, shares.view, loan_application.view, loans.view, loan-repayments.view |

**Permissions:** 31 modules × 11 actions (`view,create,update,delete,assign,approve,export,import,post,reverse,manage`) = 341 permission names (e.g. `member.view`, `loan_application.approve`, `loan-repayments.create`, `accounting.post`). Permission names are stored in DB and checked via Spatie `can()`. Spatie's `register_permission_check_method` hooks permissions into the Laravel Gate.

**Authorization mechanics (observed):**
1. **Route-level** `permission:` middleware is used only once (`contact_message.view`). Everything else uses `$this->authorize(...)` → policy.
2. **Policy pattern** — 27 policies mapped in `AuthServiceProvider::$policies`:
   - Collection abilities = plain `can()` checks (`MemberPolicy::viewAny`).
   - Instance abilities = **Super Admin bypass → org separation → branch refinement**: `if hasRole('Super Administrator') return true; if !$model->organization_id return false; return OrganizationContext::userBelongsToOrganization($model->organization_id, $auth)` (see `LoanApplicationPolicy:16-25`). Member/branch resources add a branch tier (`MemberPolicy:16-32`).
   - **Composite custom abilities** = `can('permission') && $this->view(...)` (e.g. `LoanApplicationPolicy::approve`).
3. **`Gate::before`** at `AuthServiceProvider.php:107-111`:
   ```php
   Gate::before(function (User $user) {
       if ($user->hasRole('Super Administrator') || $user->hasRole('Organization Administrator')) return true;
   });
   ```
   ⚠️ **CRITICAL FINDING:** This global bypass applies to **Organization Administrator**, granting org admins the same unrestricted ability as Super Admin *across the whole platform*, before any policy runs. Consequences verified in code: an org admin can `DELETE` arbitrary users (`UserController::destroy` → `UserPolicy::delete` says Super-Admin-only but is short-circuited), edit any user incl. Super Admins (`UserController::update`), assign the `Super Administrator` role to arbitrary new users (`UserController::store/update` + `StoreUserRequest` permits any role) — full privilege escalation, and unrestricted RBAC edits (`RoleController`). This must be remediated BEFORE AI tooling relies on policies (see §L-R1).

### A.4 Tenant isolation (soft tenancy — no global scopes)

Hierarchy enforced by data columns, not model global scopes:

```text
Platform
   ↓ organizations
Organization        (organizations.id)
   ↓ "organization_id"
Branch              (branches.organization_id)
   ↓ "branch_id"
VICOBA Group        (vicoba_groups.branch_id, vicoba_groups.organization_id)
   ↓ "vicoba_group_id"
Member              (members.organization_id / branch_id / vicoba_group_id / user_id)
   ↓ "member_id"
Savings/Share/Welfare Account, Loan, LoanApplication, LoanRepayment, ...
```

**Enforcement points (the pattern a future AI tool must follow):**
- `app/Services/OrganizationContext.php` — the shared convention: `getUserOrganizationIds()` (user→`organization_user` pivot), `userBelongsToOrganization()` (Super Admin always true), `scopeToUserOrganizations($query)` (generic `whereIn('organization_id', $orgIds)`; Super Admin unscoped), `modelBelongsToUserOrganization()`, `authorizeOrganization()` (abort 403).
- **Policies** perform the actual row-level org/branch checks.
- **List services** scope per role: `UserService::getFilteredQuery`, `MemberService::getForUser` (Super→all; Org Admin→org; Branch Manager→branch; else org OR branch), `DashboardService::resolve` (role switch with per-role widget scoping).
- **Member portal** double-filters every query by `member_id` AND `organization_id`, and enforces inline ownership (`abort_unless($kin->member_id === $member->id, 403)`).
- `branch_user` pivot is the branch-level boundary; `organization_user` pivot is the org-level boundary.
- **No global `ScopedByTenant` model trait.** Tenancy is "soft": any query path that forgets `OrganizationContext` / policy checks could cross tenant boundaries. The AI tool layer must treat tenant checks as mandatory, not rely on query conveniences.

### A.5 Finance module (Savings / Shares / Welfare)

**Authoritative flow (identical across all three):**
```text
Controller → Service → DB::transaction {
    account lockForUpdate → validate (active, amount>0, product rules, min balance)
    → Transaction::create(tx_number via TransactionNumberGenerator; status completed)
    → account->update(current_balance / total_value)
    → AuditService->log('<domain>.<action>', tx, [], diffs)
    → AccountingEventService->recordXxx(tx)   // idempotent journal
}
```
- Services: `SavingsTransactionService` (deposit/withdraw/reverse), `ShareTransactionService` (purchase/redeem/reverse), `WelfareTransactionService` (contribute/benefit/reverse), `WelfareBenefitRequestService` (create/approve/reject request; approve delegates the cash movement back to `WelfareTransactionService::benefit`).
- **Balances are STORED COLUMNS**, not computed: `savings_accounts.current_balance`, `welfare_accounts.current_balance`, `share_accounts.total_shares`/`total_value`; each transaction also stores `balance_before`/`balance_after`. There are **no model-side computed balance methods** — the AI must never derive a balance from transactions; it must read the stored column / authoritative summary.
- **The exact read API for an AI member summary exists:** `FinancialStatementService::getMemberFinancialSummary(Member $member)` → `['total_savings', 'total_shares', 'total_share_value', 'welfare_balance']` (`app/Services/FinancialStatementService.php:79-92`).
- Reversals set `transaction_type=Reversal`, mark original `Reversed`+`reversed_by`, and reverse the source journal through `AccountingEventService::reverseSourceJournal`.
- Number generation is **global, not tenant-scoped** (sequence per prefix) — informational only.

### A.6 Loan module

Models: `LoanPlan`, `LoanApplication` (+guarantors, collaterals, collateral snapshot, approvals), `LoanApprovalLevel`, `Loan` (+disbursements, repayment schedules, repayments, allocations), `LoanPlanCollateralRule`, `CollateralDocument`. All carry `organization_id`/`branch_id`/`member_id` and have `forOrganization` scopes + policies.

**Authoritative services (AI must call these, not re-derive):**
- **Eligibility** → `LoanEligibilityService::checkEligibility(Member, LoanPlan, amount, ?term): EligibilityCheckResult` — returns `{memberName, planName, requestedAmount, approvedAmount, eligible, checks[pass|fail], failureReasons, activeLoanCount}` (DTO at `app/DataTransferObjects/EligibilityCheckResult.php`). Pure read-only computation.
- **Guarantor eligibility** → `GuarantorEligibilityService::canGuarantee(Member, ?LoanApplication, ?excludeId): array{eligible, reason}` (+ `getEligibility`, `isEligible`).
- **Collateral requirement** → `CollateralRequirementService::getRequirement(LoanPlan, amount)` / `validateCollateral(LoanApplication)` / `calculateRequiredValue`.
- **Application lifecycle** → `LoanApplicationService` (`create`, `submit`, `cancel`, `addToReview`, `addGuarantor`/`removeGuarantor`/`respondToGuarantor`, `addCollateral`… `verifyCollateral`, `addCollateralDocument`).
- **Approval** → `LoanApprovalService::approve/reject` (+`getRequiredLevel`). **Note:** approval is a single-action transition `UnderReview → Approved|Rejected`; the multi-level "levels" are amount brackets recorded on the approval row, not sequential multi-approver steps.
- **Disbursement** → `LoanDisbursementService` (`createLoanFromApplication`, `createDisbursementRecord`, `confirmDisbursement` — posts journal `Dr Loans Receivable / Cr Cash`, `rejectDisbursement`, `cancelLoan`).
- **Schedules** → `LoanRepaymentScheduleService::generateSchedule` (interest via `FlatInterestCalculator` or `ReducingBalanceInterestCalculator`).
- **Repayments** → `LoanRepaymentService::postRepayment` (+idempotency key) and `reverseRepayment`. Allocation order per installment (**earliest due_date first**): **late fee → interest → principal** (`LoanRepaymentAllocationService::allocatePayment`).
- **Delinquency / PAR** → `LoanDelinquencyService::updateLoanDelinquency`, `getDaysPastDue`, `getPAR(orgId, threshold)`, `getCollectionRate`, `getPrincipalOutstanding`. **Not on a schedule** — must be run on-demand or wired to the scheduler.
- **Enums** (all string-backed in `app/Enums/`): `LoanApplicationStatus` (draft,submitted,under_review,approved,rejected,cancelled), `LoanStatus` (approved,pending_disbursement,disbursed,active,completed,cancelled), `LoanScheduleInstallmentStatus` (pending,partial,paid,overdue,waived), `LoanRepaymentStatus` (posted,reversed), `GuarantorStatus`, `LoanCollateralStatus`, `ApprovalAction`, `LoanDisbursementStatus`, `InterestMethod`, `RepaymentFrequency`, `LoanPurpose`, `CollateralType`, `CollateralDocumentType`.

**Gap:** no production code path ever sets `LoanStatus::Completed` (enum allows `Active→Completed`; tests assert it; no service does it). Relevant to lifecycle predictions.

### A.7 Accounting module

**Writes (authoritative, never to be bypassed):**
- `ChartOfAccountsService` (create/update/deactivate/delete; `initializeDefaultChart` seeds the 1000–5200 COA).
- `AccountingPeriodService` (create/close; close blocked if draft journals exist).
- `JournalPostingService` (`createDraft`, `postEntry`, `createAndPost`) — validates debit=credit, open period, account ownership; audits `journal_entry.created/posted`.
- `JournalReversalService::reverseJournal` — only Posted, not-reversed, open period.
- `AccountingConfigurationService` — mapping keys (cash_on_hand, bank_account, mobile_money, loans_receivable, interest_receivable, fees_receivable, member_savings, welfare_funds_payable, share_capital, retained_earnings, loan_interest_income, loan_fees_income, other_income, operating_expenses) → COA accounts (per org); `getAccountId(orgId, key)` auto-inits chart+mappings.
- `AccountingEventService` — **the business→journal mapper** (`recordSavingsDeposit|Withdrawal`, `recordSharePurchase|Redemption`, `recordWelfareContribution|Benefit`, `recordLoanDisbursement`, `recordLoanRepayment`, `recordRepaymentReversal`, `reverseSourceJournal`). Every mapper is idempotent via `hasPostedJournal(Class, id)`.

**Reads (the APIs an AI tool should use):**
- `TrialBalanceService::generate(orgId, ...)`; `IncomeStatementService::generate(orgId, ...)`; `BalanceSheetService::generate(orgId, ...)`; `GeneralLedgerService::getLedger / getAccountBalance(orgId, accountId) / getAccountBalances(orgId)`; `CashBankLedgerService::generate(orgId, accountId, ...)`; `FinancialStatementService::{getSavingsStatement|getShareStatement|getWelfareStatement|getMemberFinancialSummary}`.
- Balances are **derived on read** from posted `JournalLine` debits/credits (optionally date/period filtered); accounting periods act as posting gates, not balance buckets.

### A.8 Audit system

- `AuditService::log(string $event, ?Model $model, array $old, array $new): AuditLog` — actor `auth()->id()`, polymorphic `auditable_type/id`, JSON diffs, `ip_address`, `user_agent`.
- **`audit_logs` has NO `organization_id` column** — tenant context exists only via the auditable model. ~94 call sites across services and controllers; event names are `<domain>.<action>` (e.g. `savings.deposit`, `loan_application.approved`, `journal_entry.posted`, `user.login`).
- A small set of convenience wrappers exists (`logLogin`, `logUserCreated`, `logRoleAssigned`, …).

### A.9 Dashboards & navigation

- **Admin:** `resources/views/layouts/app.blade.php` + `sidebar.blade.php` (sections: Main, Organization, Members, Finance [Savings/Shares/Welfare], Loans, Reports, Accounting, Administration) and `navbar.blade.php`. Views mix Bootstrap 5 (`d-flex/card vicoba-card`) with a custom VICOBA theme; SweetAlert2 + vanilla JS confirm/fetch-403 interceptors are global in the admin layout. No Alpine, no jQuery. **Insertion point for an admin "AI Assistant":** a new `.sidebar-section` + `nav-link` right after the Dashboard link (`sidebar.blade.php:26-29`) or above the footer.
- **Member:** `layouts/member.blade.php` + `member-sidebar.blade.php` (Dashboard, My Finance, Loans, Guarantor, Statements, Activity, Account). **Insertion point for member "AI Assistant":** after the `Activity` section (`member-sidebar.blade.php:117-123`).
- **Dashboard data:** `DashboardService::resolve()` role-switches into `super_admin` / `org_admin` / `branch_manager` / `staff` / `member` widget sets in one `dashboard/index.blade.php` (815 lines). `MemberDashboardService` provides the member-aggregate summaries used by `member/dashboard.blade.php`. These two services are the natural "quick-intelligence" read APIs.

### A.10 Tests & test conventions

- `tests/TestCase.php`: **CSRF globally disabled** for feature tests; each class opts into `RefreshDatabase`.
- `tests/Traits/HasAccountingSetup.php` — `setUpAccountingFor(User, Organization)` initializes chart + mappings + open period (the standard ledger test bootstrap).
- `tests/Feature/TenantIsolation/TenantIsolationTest.php` — canonical multi-org test: seed `RolePermissionSeeder`, two orgs + branches + admins attached via pivots, Super Admin, asserts 403 on cross-tenant reads/writes and Super Admin 200 bypass.
- Member-portal recipe (`MemberPortalTest.php`): create roles on demand (`Super Administrator`, `VICOBA Member`), `createMemberWithUser()` (user + `Member` factory + `assignRole('VICOBA Member')`), then `actingAs` + route assertions incl. isolation (`assertSee/assertDontSee` on balances).
- 27 model factories exist (all domains). `phpunit.xml` uses `sqlite :memory:`, `array` cache/session, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`.

### A.11 Platform & integration notes

- `.env`: `APP_TIMEZONE=Africa/Dar_es_Salaam`, `APP_CURRENCY=TZS`, `DB_CONNECTION=mysql`/`finance`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`, Redis credentials present but unused for cache.
- `.env` already contains `MONGIKE_API_KEY` / `MONGIKE_BASE_URL` (**a Tanzanian payments API**) with **no code consumer yet** — evidence the project intends to integrate external SaaS APIs; AI provider integration should follow a similar isolated service pattern.
- `app/Repositories/` is empty. `app/DataTransferObjects/` holds the single `EligibilityCheckResult` DTO (readonly constructor promotion style). `app/Actions/` and `app/DTOs/` are empty placeholders. Enum convention: string-backed with `label()`, `values()`, and business helpers (`isCredit()`, `canTransitionTo()`, `normalBalance()`).

---

## B. AI INTEGRATION MAP (how AI reaches authoritative data)

```text
   Authenticated User (web guard session)
                 │
                 ▼
   AI Assistant (conversation layer: app/AI/Services)
                 │
                 ▼
   AI Agent (member / staff / admin persona — app/AI/Agents)
                 │
                 ▼
   AI Tool (read-only — app/AI/Tools)   ──►  AiToolPolicy + OrganizationContext (MANDATORY)
                 │
                 ▼
   EXISTING Laravel Service (authoritative; never replaced)
   ----------------------------------------------------------------
   MemberService / FinancialStatementService::getMemberFinancialSummary
   LoanEligibilityService::checkEligibility
   GuarantorEligibilityService::canGuarantee
   CollateralRequirementService::getRequirement
   LoanApplicationService / LoanApprovalService / LoanDisbursementService
   LoanRepaymentService / LoanRepaymentScheduleService / LoanDelinquencyService::getPAR
   SavingsTransactionService / ShareTransactionService / WelfareTransactionService (reads only)
   TrialBalanceService / IncomeStatementService / BalanceSheetService
   GeneralLedgerService / CashBankLedgerService / AccountingPeriodService (reads only)
                 │
                 ▼
   EXISTING Models → MySQL
   ----------------------------------------------------------------
   Backed by policies → AuditService::log (or dedicated AI audit trail)
```

Rules enforced by this map:
1. AI **never** writes financial data. Future write-capable tools must route exclusively through the existing services listed above, after human confirmation.
2. AI **never** computes balances/ledgers from raw transactions; it consumes stored columns and report-service output.
3. AI **never** re-implements eligibility, guarantor, collateral, accounting period, or journal rules; it calls the DTO-returning services and *explains* results.
4. Every tool call passes an actor + tenant check identical to a controller resource route.

---

## C. AI MODULE ARCHITECTURE (proposed directory structure)

The proposal below matches existing conventions (domain services in `app/Services`, readonly DTOs in `app/DataTransferObjects`, string-backed enums in `app/Enums`, policies in `app/Policies`, nested domain folders). It introduces a top-level `app/AI/` namespace for the intelligence layer only.

```text
app/
    AI/
        Agents/                       # persona orchestration
            AiAssistantAgent.php          # generic orchestrator (tools + provider + memory)
            MemberAiAgent.php             # member persona (own-data only)
            StaffAiAgent.php              # loan/credit/treasury/accounting officer persona
            AdminAiAgent.php              # org-admin / super-admin analytics persona
            LoanIntelligenceAgent.php     # eligibility/PAR/delinquency explanation
            FinancialIntelligenceAgent.php# accounting report interpretation
        Tools/
            Contracts/
                AiToolInterface.php        # execute(Context): AiToolResult
                ReadOnlyTool.php           # base: forces read-only + sanitizes output
            Registry/
                AiToolRegistryService.php  # tool name → definition lookup
            Member/                       # get_member, get_member_financial_summary, ...
            Loans/                        # check_loan_eligibility, get_member_loans, ...
            Accounting/                   # get_trial_balance, get_income_statement, ...
            Portfolio/                    # get_portfolio_summary, get_par, overdue scans
        Services/
            AiProviderService.php         # provider driver abstraction (contract)
            Drivers/
                AiProviderContract.php
                OpenAiCompatibleDriver.php # OpenAI / Azure / DeepSeek / Ollama / vLLM
                AnthropicDriver.php       # optional later
            AiConversationService.php     # create/append/trim conversations
            AiToolCallService.php         # execute + authorize + audit a tool invocation
            AiContextBuilderService.php   # actor/tenant/member resolution into Context DTO
            AiResponseFormatterService.php# standard financial answer envelope (see §20)
            AiGuardrailService.php        # prompt-injection + policy checks
            AiFeedbackService.php         # capture + evaluate feedback
            AiRecommendationService.php   # recommend + accept/reject workflow
            AiAnomalyService.php          # rule-based anomaly detection over existing reads
        Knowledge/
            DocumentIngestionService.php
            ChunkingService.php
            EmbeddingService.php          # provider-driven embeddings
            VectorSearchService.php       # MySQL cosine (org-scoped) with optional Qdrant driver
            KnowledgePrecedenceService.php# global → org → branch → group precedence
        Prompts/
            system_member.md
            system_staff.md
            system_admin.md
            guardrails.md
            tool_policy.md
        Policies/
            AiToolPolicy.php             # permission + tenant permit on each tool
            AiConversationPolicy.php
        DTOs/
            AiContext.php                # actor, role, orgIds, branchIds, member, tenant, rate
            AiToolDefinition.php
            AiToolResult.php             # {ok, data, warnings, source_references, sanitized, audit}
            AiResponse.php               # standard envelope (answer/reasoning/data/warnings/sources/tags)
            AiRecommendation.php
            AiEvaluation.php
        Enums/
            AiToolScope.php              # member | org | branch | group | global
            AiResponseTag.php            # system_fact | ai_interpretation | ai_recommendation
            AiRecommendationStatus.php
            AiAnomalySeverity.php
        Knowledge/ (data-layer models live in app/Models, mirroring current convention)
```

Rationale for deviations/decisions:
- **Models stay in `app/Models`** (current universal convention), not under `app/AI` — keeps Eloquent in one namespace.
- **`app/AI/Tools`** rather than `app/Services/AiTools/*` — isolated tool contract improves security review surface.
- **Policies in `app/Policies`** to match auto-discovery expectation (`AuthServiceProvider`).
- Empty `app/Actions/`, `app/DTOs/`, `app/Repositories/` are **not** adopted; existing working convention is `Services + DataTransferObjects`.

---

## D. AI DATABASE DESIGN (proposed tables — NOT created)

All tenant-bearing tables carry `organization_id` (+ `branch_id` where relevant). Every table timestamps. Money/JSON columns follow existing MySQL conventions. New tables are additive; `audit_logs` is extended (additive column) rather than duplicated.

**D.1 `ai_conversations`**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK users | actor |
| member_id | FK members nullable | set when actor is a member |
| organization_id | FK orgs nullable | resolved from `OrganizationContext::getUserOrganizationIds()` (first/primary); Super Admin may omit |
| branch_id | FK branches nullable | resolved only if single-branch scope |
| persona | string(20) | member \| staff \| admin |
| title | string(255) | generated |
| status | string(20) | open \| closed |
| model | string(100) | provider model used |
| provider | string(50) | |
| total_tokens_in / total_tokens_out | bigint | cost tracking |
| last_active_at | datetime | index |
Indexes: `user_id`, `organization_id`, `status`, `last_active_at`.
Retention: configurable (default 12 months) then purge via job.
Privacy: content is conversation text only; financial facts are references (`record_references`), never duplicated body copies.

**D.2 `ai_messages`**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| conversation_id | FK ai_conversations | index |
| role | string(20) | user \| assistant \| system \| tool |
| content | text | sanitized assistant / raw user text |
| tool_call_id | string(64) nullable | |
| tool_calls | json nullable | provider-issued tool call list |
| tokens_in / tokens_out | int | |
Indexes: `(conversation_id, id)`, `role`.

**D.3 `ai_tool_calls` (the AI audit trail — carries tenant context)**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| conversation_id | FK | |
| message_id | FK ai_messages nullable | |
| user_id | FK users | actor |
| organization_id | FK orgs | resolved at execution time (never trusted from model text) |
| branch_id | FK branches nullable | |
| member_id | FK members nullable | |
| tool_name | string(80) | index |
| arguments | json | sanitized (input IDs only, no free text pins) |
| status | string(20) | succeeded \| failed \| unauthorized \| forbidden \| hallucination |
| result_summary | json nullable | truncated + field-filtered |
| source_references | json nullable | record ids/numbers surfaced to user |
| latency_ms | int | |
| error | string(500) nullable | non-sensitive |
| created_at | datetime | index `(organization_id, tool_name, created_at)` |
Audit: row is written for **every** tool attempt, including denied ones (denied rows capture `status=forbidden` and the deny reason in `error`). This satisfies "AI tool access is traceable" without touching `audit_logs`; optionally mirror key events to `AuditService::log('ai.tool_call', ...)`.

**D.4 `ai_feedback`**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| conversation_id | FK | |
| message_id | FK | |
| user_id | FK | |
| rating | tinyint(1-5) nullable | |
| verdict | string(20) nullable | helpful \| incorrect \| hallucinated \| unsafe \| unhelpful |
| comment | text nullable | |
| created_at | datetime | unique `(message_id, user_id)` |
Retention: keep (training candidate) with `ai_evaluations` gating.

**D.5 `ai_recommendations`**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| conversation_id FK / user_id FK | | |
| organization_id FK nullable | | |
| type | string(40) | loan_explanation \| policy_answer \| financial_summary \| anomaly_alert \| prediction_note |
| payload | json | the recommendation body |
| source_references | json | records consulted |
| status | string(20) | produced \| shown \| accepted \| rejected \| dismissed |
| accepted_by / accepted_at | FK users / datetime | human decision — only relevant records |
Indexes: `organization_id`, `status`, `type`.

**D.6 Knowledge — `ai_knowledge_documents`**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| organization_id FK nullable | NULL = global (platform) knowledge |
| branch_id / vicoba_group_id FK nullable | scoped knowledge tiers |
| title | string(255) | |
| doc_type | string(40) | constitution \| loan_policy \| savings_policy \| welfare_policy \| accounting_policy \| member_handbook \| faq \| manual \| product_doc \| other |
| version | string(20) | |
| category | string(80) nullable | |
| file_path | string(500) | private disk |
| checksum | string(64) | sha256 — tamper detection |
| status | string(20) | draft \| published \| archived |
| effective_from / effective_to | date nullable | |
| allowed_roles | json nullable | restrict doc to roles (e.g. only Auditor sees audit policy) |
| uploaded_by FK | | |
Indexes: `organization_id`, `doc_type`, `status`, `effective_from`.

**D.7 Knowledge — `ai_knowledge_chunks`**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| document_id FK | | |
| organization_id FK | copied from doc — used for search scoping |
| chunk_index | int | unique `(document_id, chunk_index)` |
| content | text | |
| token_count | int | |
| embedding_model | string(100) | version-stamped |
| embedding | blob | float32 bytes (~1.5KB @ dim 384, ~3KB @ dim 768) |
Indexes: none for embedding (no native vector type in MySQL 8); search filters by `organization_id` then cosine in SQL. See §G.

**D.8 `ai_model_versions`**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| provider / model | string | |
| version | string(100) | |
| kind | string(30) | chat \| embedding \| evaluator \| predictor |
| metadata | json | params, hash |
| active | bool | one active per kind/provider |
| created_at | datetime | |

**D.9 `ai_evaluations`** — `id, feedback_id FK, evaluator_user_id FK, verdict (approved|rejected|escalate), notes, created_at`. Gates moving feedback into a training/eval dataset; **only approved rows** may be exported.

**D.10 `ai_predictions`** — `id, organization_id FK, model_name, model_version_id FK, target (delinquency|repayment_risk|cashflow|portfolio), member_id/loan_id FK nullable, score json, features_snapshot json (data at inference time), generated_at, expires_at, status`. Snapshots make predictions auditable and reproduceable; expired rows pruned.

**D.11 `ai_anomalies`** — `id, organization_id FK, detected_by (rule id / model), type, description, severity (low|medium|high|null), related_type/related_id (morph), status (open|acknowledged|resolved|false_positive), acknowledged_by FK nullable, created_at`. Index `(organization_id, status)`.

**D.12 `audit_logs` extension (additive, safe):**
- Add `organization_id` FK nullable + index. This lets every existing AND new audit event be tenant-filterable (also fixes a pre-existing limitation for general auditing, not just AI).
- Recommended AI events through the existing service: `ai.conversation_started`, `ai.conversation_closed`, `ai.tool_invoked`, `ai.recommendation_produced`, `ai.recommendation_accepted`, `ai.recommendation_rejected`, `ai.feedback_submitted`, `ai.answer_shown`. `ai_tool_calls.*` carries the record-level detail; `audit_logs` gets coarse-grained actor/tenant events.

Justification to avoid duplication: no other table re-creates RBAC, audit, or financial records; all AI tables are *conversation/intelligence/log artifacts*.

---

## E. AI TOOL REGISTRY

**Authorization legend:** every tool performs `AiToolPolicy::allow($actor, $tool, $target)` = permission check (`can(...)`) **AND** tenant permit (`OrganizationContext` / member ownership). Member personae can always address only their own `member_id` (enforced by `AiContextBuilder` reading `$user->member`, not from the conversation text). Default is **deny**.

| Tool | Purpose | Authorized roles | Tenant scope | Read/Write | Existing service/model used | Audit |
|---|---|---|---|---|---|---|
| `get_member` | Load member profile (basic identity) | Staff: BranchMgr, LoanO, CreditO, Treas, Accnt, Sec, CollO, Auditor, OrgAdmin, SuperAdmin | Org/branch scope via `MemberPolicy::view` | RO | `Member` model + `MemberPolicy` | Yes |
| `get_my_profile` | Member's own profile | VICOBA Member | Own member only | RO | `$user->member` | Yes |
| `get_member_financial_summary` | Savings+Shares+Welfare totals | Staff above (target member) / Member (own) | Org scope / own member | RO | `FinancialStatementService::getMemberFinancialSummary` | Yes |
| `get_savings_summary` | Per-account savings detail | Staff (account scope via `SavingsAccountPolicy`) / Member (own) | Org/branch/own | RO | `SavingsAccount` + stored `current_balance`; `FinancialStatementService::getSavingsStatement` | Yes |
| `get_share_summary` | Share balance & value | Staff / Member own | Org/branch/own | RO | `ShareAccount` (`total_shares`, `total_value`) | Yes |
| `get_welfare_summary` | Welfare balance & requests | Staff / Member own | Org/branch/own | RO | `WelfareAccount.current_balance`; `WelfareBenefitRequest` | Yes |
| `get_member_loans` | Member's loans + status | Staff / Member own | Org/own | RO | `Member::loans()` / `LoanDisbursementService::getLoansForMember` | Yes |
| `get_loan_details` | Single loan incl. schedule summary | Staff via `LoanPolicy::view`, Member own | Org/own | RO | `Loan` + `LoanRepaymentScheduleService::getScheduleSummary` | Yes |
| `get_repayment_history` | Repayment ledger for a loan/member | Staff via `LoanRepaymentPolicy::view`, Member own | Org/own | RO | `LoanRepaymentService::getRepaymentsForLoan` | Yes |
| `get_loan_application` | Application detail incl. approval rows | Staff via `LoanApplicationPolicy::view`, Member own | Org/own | RO | `LoanApplication` + `approvals()` + `eligibility_snapshot` | Yes |
| `check_loan_eligibility` | Run/explain authoritative eligibility | LoanO, CreditO, BranchMgr, OrgAdmin, SuperAdmin, Member (own) | Org/own | RO (pure computation, no write) | `LoanEligibilityService::checkEligibility` | Yes |
| `check_guarantor_eligibility` | Guarantor canGuarantee answer | Staff/LoanO/CreditO, Member (self-as-guarantor) | Org (same-org rule enforced by service) | RO | `GuarantorEligibilityService::canGuarantee` | Yes |
| `check_collateral_requirement` | Collateral requirement / adequacy | BranchMgr, LoanO, CreditO, OrgAdmin, SuperAdmin | Org | RO | `CollateralRequirementService::getRequirement/validateCollateral` | Yes |
| `get_next_repayment` | Next due + overdue installments | Member (own), CollO/staff | Own / org | RO | `LoanRepaymentSchedule` (status pending/overdue, earliest due_date), `MemberDashboardService::loanSummary` | Yes |
| `get_payment_due_reminders` | Upcoming payments list | Member own; CollO staff scope | Own / org | RO | `LoanRepaymentSchedule` filtered + `LoanDelinquencyService` | Yes |
| `get_group_financial_summary` | Group-level aggregates | BranchMgr, OrgAdmin, SuperAdmin; Secretary (group/member) | Group/org | RO | NEW read-only reader built on `VicobaGroup` + accounts sum (must follow `DashboardService` scoping pattern) | Yes |
| `get_portfolio_summary` | Loan portfolio + PAR buckets | BranchMgr, CollO, CreditO, LoanO, OrgAdmin, SuperAdmin | Org/branch | RO | `LoanDelinquencyService::getPAR/getDelinquentLoans` + loan aggregations | Yes |
| `get_trial_balance` | Trial balance (authoritative) | Treasurer, Accountant, OrgAdmin, SuperAdmin, Auditor | Org | RO | `TrialBalanceService::generate` | Yes |
| `get_income_statement` | Income statement | Treasurer, Accountant, OrgAdmin, SuperAdmin, Auditor | Org | RO | `IncomeStatementService::generate` | Yes |
| `get_balance_sheet` | Balance sheet | Treasurer, Accountant, OrgAdmin, SuperAdmin, Auditor | Org | RO | `BalanceSheetService::generate` | Yes |
| `get_cash_ledger` | Cash/bank ledger | Treasurer, Accountant, OrgAdmin, SuperAdmin | Org | RO | `CashBankLedgerService::generate` | Yes |
| `get_general_ledger` | Account ledger / account balance | Accountant, Treasurer, OrgAdmin, SuperAdmin, Auditor | Org | RO | `GeneralLedgerService::getLedger/getAccountBalance` | Yes |
| `get_accounting_period_status` | Open/closed periods | Accountant, OrgAdmin, SuperAdmin | Org | RO | `AccountingPeriodService::getCurrentPeriod/getPeriods` | Yes |
| `get_member_documents` | Member's uploaded documents list (metadata only; file download requires policy) | Member own; Staff via `MemberPolicy`+document policy; Auditor read | Own / org | RO | `Member::documents()`, `MemberDocument` | Yes |
| `search_knowledge` | RAG retrieval over authorized knowledge | All roles; result scoped by knowledge tier + `allowed_roles` | global→org→branch→group precedence | RO | `Ai/Knowledge/*` (Phase 11.5) | Yes |
| `get_audit_history` | Explain audit trail for a resource | Auditor, OrgAdmin, SuperAdmin (should also honor `audit.view`) | Org | RO | `AuditLog` filtered by new `organization_id` | Yes |

**Hard rules for every tool:**
- All 26 tools above are **READ-ONLY**. The registry rejects any tool without `ReadOnlyTool` base.
- Inputs are strongly typed IDs (member_id, loan_id, application_id, org_id, account_id, period_id) — **never raw SQL, never free-form SELECT**, never collection filters from free text.
- Output is passed through a sanitizer (field allowlist per tool; numeric fields only; no `password`, no `national_id` stock unless role requires it, no full document content, no raw audit JSON in full).
- Each tool stamps `source_references` (record identifiers it read, e.g. account numbers, journal numbers, report name + as-of date) so the answer can cite system records.

**Future write-capable tools (NOT in 11.0, gated to a later phase, human-confirm only):** suggest `loan_application_review`, `guarantor_approve`, `collateral_verify` — each must call `LoanApplicationService`/`GuarantorEligibilityService`/`CollateralRequirementService` and require explicit user confirmation in UI; never an AI auto-execute.

---

## F. SECURITY ARCHITECTURE

### F.1 Authorization & tenant isolation model for AI

```text
User request ──► auth (web guard session)
              ──► AiContextBuilderService resolves:
                    actor = auth()->user()
                    role set = $user->getRoleNames()
                    member = $user->member (member persona)
                    orgIds = OrganizationContext::getUserOrganizationIds()
                    branchIds = $user->branches()->pluck(...)
              ──► AiToolCallService:
                    look up tool definition in registry
                    AiToolPolicy::allow(actor, tool, parsed target)  → permission + tenant permit
                    tool->execute(AiContext)                          → only calls documented services
                    sanitize output                                   → AiToolResult
                    write ai_tool_calls row                          → audit
                    return result to agent
```

- **No AI bypass exists.** The AI never queries Eloquent directly; it always passes through the tool layer, which itself passes through the same policy/tenant helpers as controllers.
- **Actor identity is never the model's interpretation.** The `member_id`/`orgId` used for scoping come from the resolved `AiContext`, not from conversation text.
- **Tenant scope rules:**
  - Member persona: hard-pinned to `$user->member->id`; tools ignore any other member id.
  - Staff persona: `whereIn('organization_id', orgIds)` (Super Admin unscoped); branch-level tools additionally `whereIn('branch_id', branchIds)` for Branch Manager; member lookups go through `MemberService::getForUser` or policy.
  - Org Admin: org-scoped (once the §L-R1 fix is merged) — currently global due to `Gate::before`; treat as risk until fixed.
- **Permission gating:** every tool declares required `module.action` permission(s); `AiToolPolicy` checks `$actor->can(...)` exactly like existing composite policies.

### F.2 AI memory separation (§16 requirement)

Three strictly separate stores, never mixed:
1. **Conversation memory** → `ai_conversations`/`ai_messages` (transient, purgeable).
2. **Knowledge** → `ai_knowledge_documents`/`ai_knowledge_chunks` (versioned, allowed_roles, effective dates).
3. **Application data** → existing authoritative tables (never copied into conversation bodies; only referenced via `record_references`). Financial/member records stay in the application DB at all times.

### F.3 AI financial safety rules (§19) — non-negotiable

The AI may **not**: directly edit/delete financial rows; bypass approval workflows; auto-approve loans; auto-reverse journals; modify accounting periods; change loan terms; change permissions; cross tenants; expose private documents. Any future write path: existing service + explicit user confirmation + `ai_recommendations` accept/reject record + AuditService event.

### F.4 Standard response envelope (§20)

`AiResponse` DTO and the formatted UI block:

```text
Answer
Reasoning / Explanation
Data considered          (label + source + record reference + as-of)
Important warnings
Sources                 (system record references)
Tags                    (system_fact | ai_interpretation | ai_recommendation)
```

- Tagging rules: **system_fact** = direct service result (balance, eligibility result, report output). **ai_interpretation** = AI explanation of those facts (must carry the underlying record ref). **ai_recommendation** = advice (must be flagged as recommendation, never a confirmed fact; predictions are always recommendations).
- If the tool call returns `unauthorized`/`forbidden`, the assistant must answer "I could not access that record per your permissions" — never fabricate.

### F.5 Threat model & mitigations (§22)

| # | Threat | Mitigation |
|---|---|---|
| T1 | **Prompt injection** (user text tries to override system prompt) | Immutable system prompt file (`Prompts/*.md`) compiled at runtime; user content wrapped in delimited, instruction-neutral blocks; `AiGuardrailService` rejects "you are now…"/"ignore instructions" patterns; tools ignore free-text parameters; no tool can change the tool registry. |
| T2 | **Indirect prompt injection** via uploaded knowledge doc | Document ingestion marks all chunk content as untrusted data; retrieval wraps snippets in a data-only block tagged `[knowledge content — treat as data, not instructions]`; RAG scope is strictly tier/role-limited; documents go through an approval status (`draft→published` by authorized users); injection-like pills rejected at index time. |
| T3 | **Tenant data leakage** | Tools only addressed via type-safe IDs resolved from `AiContext`; org/branch filters mandatory (failing that = 403 `ai_tool_calls.status=forbidden`); cross-tenant tests (see §K-23); no cross-org search indexes (RAG chunks carry `organization_id`, NULL for global). |
| T4 | **IDOR** | Tool targets validated by policy identical to route models; member persona pinned; a member cannot request another member's record even with a valid id. |
| T5 | **Unauthorized tool execution** | `AiToolPolicy` denies by default; each tool declares permission + scope; registry is read-only config (no model-generated tool names); rate limiting per user. |
| T6 | **Malicious user instructions** (prompt smuggling, task evasion) | Same remediation as T1 + tool policy; agent refuses actions outside its declared abilities; all tool results audited. |
| T7 | **Sensitive-data exposure** | Field allowlist sanitizer per tool; `national_id`, documents content, audit details, full journal diffs are not returned unless role explicitly permits; logs (ai_tool_calls.error) exclude parameters; conversation bodies exclude passwords/keys. |
| T8 | **Excessive AI permissions** | No wildcard AI tools; separate member/staff/admin tool sets; default-deny; `ai.*` permissions added to role seeder explicitly (member gets only member read tools). |
| T9 | **Hallucinated financial values** | All figures must originate from a tool result; answer template only allows numbers that carry a `source_references`; `system_fact` tag; "no data" instructions when tool not invoked; guardrail compares AI-summarized figures against tool snapshot before display (Phase 11.3). |
| T10 | **Manipulated knowledge documents** | sha256 checksum + stored file on private disk; only trusted roles can publish; version + effective_from/to; archived not deleted; org-scoped precedence is data-driven, not model-influenced. |
| T11 | **Poisoned feedback** | Feedback is human-authorial (authenticated user); `ai_evaluations` requires a second authorized evaluator verdict before a sample enters training; feedback alone never mutates runtime behavior. |
| T12 | **Malicious file uploads** | Reuse existing document validation; PDF/Word only; size/mime limits; store on private disk; malware scan hook if available; never feed files to model until parsed/approved. |
| T13 | **Provider/model data retention** | Provider abstraction permits `retention`/zero-data-retention options where exposed; chat requests omit sensitive fields (send only referenced, sanitized data per tool contract); customers can disable cloud provider; local driver (Ollama/vLLM) supported; data-retention documented per org. |
| T14 | **Logging sensitive information** | ai_tool_calls stores sanitized result summary + refs only; conversation content stored app-side with retention; `LOG_CHANNEL=stack`/`single` — structured logs exclude message bodies; audit row values are diffs like existing convention. |

### F.6 Cost, caching, rate limiting, sync vs async (§24)

- **Token accounting:** per-message and cumulative `tokens_in/out` on `ai_messages`/`ai_conversations`; model/provider stamped. Daily budgets per org (config) stop conversations on breach.
- **Caching:** `Cache::remember` (store `database` today, Redis recommended later) for idempotent read-only tool results keyed by `actor+tenant+entity+as_of_date` with short TTL (e.g. 60s) — prevents repeated identical tool calls; cache RAG top-K for stable queries; **never** cache raw conversations.
- **Rate limiting:** L4 limiter on AI endpoints (`throttle` middleware) per user + per org bucket; conversation cap (config: messages/user/day); tool-call cap per conversation; klipping admin override.
- **Conversation limits:** max messages per conversation (default 50 → rollover/summarization), context-window trimming via a summarizer job, token ceiling per request.
- **Synchronous (web request):** short Q&A, single aggregate lookups, eligibility explanation, tool results ≤ report-size thresholds.
- **Queued (database queue — already configured; jobs must be created):** RAG ingestion + chunking + embedding; document parsing; heavy report generation (`full balance sheet` etc.); predictive batch scoring; anomaly weekly sweep; long multi-turn summarization; feedback-to-eval pipeline. `$job` should use `->onQueue('ai')` and be retriable; failures logged (non-sensitive).
- **AI request logging:** every provider round-trip logged (tool call row + provider latency/tokens); failed/denied calls visible in tenant-scoped audit view for Auditor.

---

## G. RAG / KNOWLEDGE ARCHITECTURE

### G.1 Pipeline

```text
Document (PDF/Word/MD) upload
  → store private disk + sha256 + version (ai_knowledge_documents, status=draft)
  → QUEUE job: parse → ChunkingService (semantic/section chunks, ~500–1000 tokens, overlap)
  → EmbeddingService (provider embedding model) → ai_knowledge_chunks (content + embedding blob)
  → publish (draft→published) by authorized role → searchable
Query path:
  user message → intent → SearchKnowledgeTool
    → resolve knowledge scope(s) for actor (see G.2)
    → embed query → MySQL cosine search over ai_knowledge_chunks WHERE organization_id IN (scopes)
    → top-K (default 4–6) chunks → KnowledgePrecedenceService orders by tier → prompt context block
```

### G.2 Knowledge scope & precedence (§15)

```text
Precedence (highest wins on conflict):  Group knowledge > Branch knowledge > Organization knowledge > Global(platform) knowledge
```
- `global` (organization_id NULL) = platform defaults (VICOBA constitution template, general policies, handbooks, FAQs).
- `organization` = org-specific constitutions/policies/product docs (overrides global).
- `branch`/`group` = area-level rules (rare, e.g. local savings conventions).
- Retrieval filter uses actor's org chain; `allowed_roles` further restricts (e.g. audit policy only to Auditor/Admin). A global loan policy never overrides an org-configured product rule because org-tier chunks are returned preferentially for the same topic.
- Retrieval results feed a **data-only context block**; the AI explains which tier it used (`Source: {org} Loan Policy v3`).

### G.3 Vector storage decision

- MySQL 8 has **no native vector type**. Realistic options for this project:
  1. **MySQL + SQL cosine (recommended, Phases 11.5–11.8):** store embedding as `BLOB` (float32) or JSON; compute inner-product on the org-filtered candidate set. VICOBA org corpora are small (tens–low-hundreds of chunks), so brute-force cosine over the org's own chunks is fast (< tens of ms) with zero new infrastructure. Encode `float32` via PHP `pack('f*', ...)`.
  2. **Qdrant / Milvus (future scale-out):** dedicated vector DB behind the `VectorSearchService` contract; swap driver when corpus > ~50k chunks or multi-org scale demands it.
  3. **pgvector** — rejected: the project is MySQL 8; introducing Postgres breaches the stack.
- Driver contract (`VectorSearchService`) keeps the app provider-agnostic between MySQL brute-force and Qdrant.

---

## H. LEARNING ARCHITECTURE

### H.1 Feedback loop (read-only runtime, human-gated training)

```text
AI answer shown ──► user feedback (ai_feedback: rating + verdict)
                        │
                        ▼
              ai_evaluations (authorized evaluator: approved | rejected | escalate)
                        │
                        ▼
         APPROVED ⟶ export to versioned eval dataset (ai_model_versions stamps provider/model/version)
```

- Feedback **never mutates** runtime prompts, policies, loan rules, interest rates, eligibility rules, accounting rules, permissions, tenant boundaries, or approval authority. That is §17's hard boundary: the AI may *recommend* changes; authorized humans approve changes through existing configuration/admin UIs.
- The eval dataset is org-scoped/version-scoped; training events are recorded (`ai_evaluations`) and only approved samples are used for future fine-tuning/distillation.
- Recommended artifacts: a `general` (or subagent) scheduled job that batches approved samples into a curated dataset export for offline use; no automatic re-training in v1.

---

## I. PREDICTIVE AI ROADMAP (§18)

Available historical data today (all in MySQL, tenant-scoped, audited):

| Candidate model | Existing features | Data quality | Minimum history | Target variable | Privacy/security | Currently feasible? |
|---|---|---|---|---|---|---|
| **Loan delinquency prediction** | `LoanRepaymentSchedule` (amount_paid, outstanding, due_date, status, days_overdue), loan/plan attributes, member profile, payment history, allocation history (`LoanRepaymentAllocation`) | Good; transactional | Need ≥ 1–2 yrs of repaid loans (likely `DELTA`; platform is early) | Probability of overdue/`days_overdue > 0` at t+k | Member-level features need pseudonymization; org-scoped models | **Not yet** — insufficient completed-loan history; feasible after ~12–18 months sustained use |
| **Repayment-risk scoring** | Per-installment payment timing, partial payments, guarantees count, auto-generated schedule | Moderate | 6–12 months of installment history | Non-payment / installment default flag | Derived from first-party data; keep org-scoped | Triage feasible soon; model maturity later |
| **Cash-flow forecasting** | `SavingsTransaction`, `LoanRepayment`, disbursement dates, welfare flows, journal cash accounts | Moderate | 12 m+ | Weekly/monthly net cash (by org) | Aggregates only, no member grain | Forecasting service (statistical baseline) feasible; ML needs history |
| **Portfolio forecasting** | Loan statuses, amounts, terms, PAR history | Moderate | per-model season | PAR30/60, portfolio size | Aggregates only | Statistical forecast now; ML later |
| **Anomaly detection** | Full transaction+journal ledger + audit trail | High | immediate start (rule-based) | Flag unusual tx/period clusters | Org-scoped, auditor-gated | **Yes now** — rule & statistical baseline (`ai_anomalies`) before any ML |

**Decision:** Phase 11.8 starts with rule/statistical baselines (delinquency risk indicators, cash-flow trend using existing `LoanDelinquencyService` + transaction sums), stored in `ai_predictions` with `features_snapshot`; ML models (XGBoost/LightGBM via external pipeline or PHP prediction runtime) are introduced only when per-tenant history thresholds are met — driven by a `ai_model_versions` gate, human-activated.

---

## J. PROVIDER RECOMMENDATION

**Decision: a driver-based `AiProviderContract` implemented with Laravel's HTTP client; default driver = OpenAI-compatible Chat Completions (`/chat/completions` with `tool_use`/`function_calling` + `response_format` JSON or structured output).**

Rationale (scored against local context):
- **Laravel compatibility:** zero new dependencies (Laravel HTTP client ships with the framework) — matches the project's minimal-$composer posture (only Spatie added so far). Packages like `openai-php/laravel` or Saloon are viable but optional.
- **Structured output:** OpenAI-compatible `response_format: json_schema` + function calling is the de-facto standard; Anthropic (tool use) and Ollama/vLLM also expose OpenAI-compatible endpoints, so **one driver covers OpenAI, Azure OpenAI, DeepSeek, Mistral-API, Ollama, vLLM** — this single compat surface is the cheapest switching strategy.
- **Tool/function calling:** supported by all OpenAI-compatible servers; this is what powers `AiToolCallService`.
- **RAG support:** embeddings endpoint (`/embeddings`) is OpenAI-compatible across local+cloud; pairs with MySQL cosine (see §G).
- **Cost / reliability (Tanzania context):** cloud models (e.g. `gpt-4o-mini` class) are cheap for short structured financial answers; data-residency and intermittent connectivity push toward a deployable local option later. The MongoDB of this: `MONGIKE` payments provider in `.env` shows local SaaS appetite; an OpenAI-compatible gateway keeps that option open.
- **Maintainability:** a single contract (`AiProviderContract`) with `chat(messages, tools, outputSchema): AiProviderResult` means the whole app depends on `AiProviderService`, not on a vendor client class.

**Contract sketch (Phase 11.1):**
```php
interface AiProviderContract
{
    public function chat(array $messages, array $tools = [], ?string $outputSchema = null): AiProviderResult;
    public function embeddings(array $texts, string $model): array; // float vectors, for RAG
    public function name(): string;
}
```
`config/ai.php` selects the active driver (`base_url`, `api_key`, `model`, `embedding_model`, `max_tokens`, `temperature`). Drivers: `OpenAiCompatibleDriver` (default), `AnthropicDriver` (optional later). Model identity is **not hard-coded** anywhere; `ai_model_versions` stamps it.

---

## K. IMPLEMENTATION ROADMAP (phases, each with objective / files / migrations / services / tests / deps / risks)

No 11.x phase begins until this audit is reviewed and approved.

### Phase 11.0 — Architecture Audit (THIS DELIVERABLE)
- Objective: document foundation; freeze the "no rebuild" contract; approve design sections A–J, L.
- Deliverables: this document. **No code.**
- Output gate: architecture review sign-off by project owner before 11.1.

### Phase 11.1 — AI Foundation
- Objective: provider abstraction + conversation store + DTOs (no business data access yet).
- Files: `config/ai.php`; `app/AI/Services/AiProviderService.php`; `app/AI/Services/Drivers/AiProviderContract.php`, `OpenAiCompatibleDriver.php`; `app/AI/DTOs/{AiContext,AiToolResult,AiResponse}.php`; `app/AI/Services/AiConversationService.php`; `app/Models/AiConversation.php`, `AiMessage.php`, `AiModelVersion.php`; middleware/throttle wiring.
- Migrations: `ai_conversations` (D.1), `ai_messages` (D.2), `ai_model_versions` (D.8), `ai_tool_calls` (D.3 incl. FK to conversations).
- Services: `AiProviderService`, `AiConversationService`.
- Tests: provider-driver contract test (fake driver asserting call shape), conversation CRUD + trimming, token accounting.
- Dependencies: none composer-wise; requires a stub OpenAI-compatible endpoint or SDK-less HTTP driver.
- Risks: provider availability in the deployment region; making sure the driver contract matches real tool-call payloads early (do a spike before hardening).

### Phase 11.2 — AI Security & RBAC
- Objective: actor/tenant resolution and default-deny tool authorization; AI permissions.
- Files: `app/AI/Services/AiContextBuilderService.php`; `app/AI/Policies/AiToolPolicy.php`; migration `audit_logs` + early `organization_id`; `database/seeders/AiPermissionSeeder.php` (adds `ai.*` permission module: `ai.assistant.use`, `ai.tool.invoke`, `ai.knowledge.manage`, `ai.feedback.submit`, `ai.audit.view`); role grants; `app/AI/Services/AiGuardrailService.php`.
- **Prerequisite:** merge the §L-R1 RBAC remediation (narrow `Gate::before` to Super Administrator only; enforce org scope for Organization Administrator through policies). AI must not launch against a permission model where an Org Admin is globally privileged.
- Migrations: `organizations`-aware `audit_logs.organization_id` (+index/backfill), seeders.
- Tests: tenant matrix (member isolation, org A↔B, branch A↔B); manager cannot use member tools; denied tool calls recorded as `forbidden`; guardrail rejects prompt-injection strings.
- Risks: unlocking the existing W1 class of vulnerabilities is required before this phase is meaningful; delaying §L-R1 will make AI tools security theater.

### Phase 11.3 — AI Tools
- Objective: read-only tool registry + execution + audit + sanitization; wire first financial tools.
- Files: `app/AI/Tools/**` (Registry + Member/Loans/Accounting/Portfolio shells); `app/AI/Services/AiToolCallService.php`; `app/AI/Services/AiResponseFormatterService.php`; `app/AI/DTOs/AiToolDefinition.php`; `app/AI/Enums/*`.
- Migrations: none new (uses `ai_tool_calls` from 11.1).
- Services: `AiToolCallService` (authorize→execute→sanitize→audit), tool classes wrapping the exact services in §E.
- Tests: tool contract; financial correctness (`get_member_financial_summary` matches `FinancialStatementService` output); unauthorized tool calls 403; hallucination guard (tool result suppression when no data); audit row written.
- Risks: forgetting tenant scope in a new read aggregator; the biggest cost is verifying each tool's output against the authoritative service.

### Phase 11.4 — AI Assistant UI
- Objective: chat surfaces for staff/admin and member portal.
- Files: `app/Http/Controllers/AiAssistantController.php` (+ `AiMemberAssistantController.php` under member portal); routes (web JSON POST endpoints under `auth|suspended` + `member` groups); views `resources/views/ai/*`; sidebar links in `layouts/components/sidebar.blade.php` (after Dashboard link, §9) and `member-sidebar.blade.php` (after Activity); member/staff/admin personas.
- Migrations: none.
- Services: reuse 11.1–11.3; new thin controller layer only.
- Tests: member sees only own data via UI; unauthorized 403; conversation persistence; rate-limit; 403-fetch interceptor path works with the existing SweetAlert2 global handler.
- Risks: streaming/SSE complexity — start with request/response (non-streaming) JSON + loading state; keep within Bootstrap 5 + vanilla JS conventions (no new frontend framework).

### Phase 11.5 — RAG
- Objective: knowledge ingestion, chunking, embeddings, MySQL cosine search with tier precedence.
- Files: `app/AI/Knowledge/*`; `app/Models/AiKnowledgeDocument.php`, `AiKnowledgeChunk.php`; upload/publish controllers + views under an authorized admin area; `app/Jobs/*` ingestion jobs; `config/ai.php` embedding settings; `SearchKnowledgeTool`.
- Migrations: `ai_knowledge_documents` (D.6), `ai_knowledge_chunks` (D.7).
- Services: `DocumentIngestionService`, `ChunkingService`, `EmbeddingService`, `VectorSearchService` (MySQL driver first, `QdrantDriver` contract-ready), `KnowledgePrecedenceService`.
- Tests: tier precedence (org overrides global); org scoping of search (org A never sees org B chunks — `organization_id` filter); `allowed_roles` enforcement; tamper checksum rejection; injection-safe retrieval (chunks rendered as data).
- Risks: embedding cost/model variance; MySQL cosine performance on big corpora (mitigate with per-org chunk set filtering); document approval workflow.

### Phase 11.6 — Feedback & Learning
- Objective: feedback capture, evaluation workflow, dataset export gate.
- Files: `app/AI/Services/AiFeedbackService.php`, `AiRecommendationService.php`; `app/Models/AiFeedback.php`, `AiEvaluation.php`, `AiRecommendation.php`; UI for thumbs/ratings on assistant + evaluator console; recommendation accept/reject flow.
- Migrations: `ai_feedback` (D.4), `ai_recommendations` (D.5), `ai_evaluations` (D.9).
- Tests: feedback idempotency (one per message); evaluator-only verdicts; approved-only export; recommendation accept records `ai.recommendation_accepted`.
- Risks: misleading UX ("AI can approve"), reputational risk if recommendations presented as facts — the envelope tagging (§20) must ship with this.

### Phase 11.7 — Financial Intelligence
- Objective: agent explanations for accounting + portfolio + anomaly reporting (rule-based).
- Files: extend `LoanIntelligenceAgent`/`FinancialIntelligenceAgent`; `app/AI/Services/AiAnomalyService.php`; `app/Models/AiAnomaly.php`; anomaly sweep job; portfolio/PAR explanation prompts.
- Migrations: `ai_anomalies` (D.11).
- Services: rule-based anomaly detection over existing read services; PAR/delinquency explain via `LoanDelinquencyService`.
- Tests: anomaly detector is a pure function over a snapshot; no false tenant crossing; Auditor-only visibility where required.
- Risks: incompletely-scheduled delinquency (see §L-R6) blunts portfolio intelligence; resolve before or with this phase.

### Phase 11.8 — Predictive Intelligence
- Objective: statistical baselines first; ML gated by data maturity.
- Files: `app/AI/Services/AiPredictionService.php`; `app/Models/AiPrediction.php`; scheduled batch scoring job; dashboard insight blocks.
- Migrations: `ai_predictions` (D.10).
- Services: delinquency-risk indicator, cash-flow trend (statistical), PAR forecast; `features_snapshot` capture; model gate in `ai_model_versions`.
- Tests: snapshot determinism; features do not include cross-tenant data; results tagged `ai_recommendation`/prediction-not-fact.
- Risks: data scarcity (see §I); presenting model outputs as facts — tagging + disclaimer mandatory.

---

## L. RISKS / ARCHITECTURAL CONFLICTS TO FIX BEFORE / WITH AI

Priority-ordered; items marked **Blocking** should be resolved **before** Phase 11.2 (RBAC-dependency of AI security). The rest are "fix before the feature they feed".

1. **R1 — BLOCKING: `Gate::before` grants Organization Administrator a global bypass (privilege escalation).** (`app/Providers/AuthServiceProvider.php:107-111`.) Verified consequences: org admin can delete/edit any user incl. Super Admins, assign the `Super Administrator` role (`UserController::store/update` + `StoreUserRequest` allows any role, no role-guard), unrestrained RBAC edits, cross-org user assignment. **Fix:** narrow `Gate::before` to `Super Administrator`; let `Organization Administrator` flow through policies; block non-Super-Admin role assignment of privileged roles in `StoreUserRequest`. Add regression tests (cross-org user delete/create-role). This is a **security prerequisite** for any AI that trusts policies.
2. **R2 — BLOCKING: `audit_logs` has no `organization_id`.** AI audit events and Auditor queries cannot be tenant-filtered. **Fix:** additive column + migration backfill from auditable rows (polymorphic walk best-effort), index.
3. **R3 — Soft tenancy (no global tenant scopes).** Every new query (including every AI read aggregator) must call `OrganizationContext`/policy. **Fix:** encode the "no raw Eloquent in AI" rule in code review; the tool contract enforces scoping by construction.
4. **R4 — Scheduled work is broken:** `routes/console.php` references nonexistent `App\Models\Repayment` and `App\Notifications\PaymentDueReminder` (notifications dir is empty). The daily scheduler task throws. **Fix:** remove/rewrite the closure using real `LoanRepayment`/schedules before relying on any `Schedule` (AI batch jobs will need a working scheduler + worker).
5. **R5 — Queue worker not established:** `QUEUE_CONNECTION=database` configured but zero `app/Jobs`; long RAG/prediction jobs will need worker ops + a queue routing (e.g. `ai` queue) and dev/prod runbooks.
6. **R6 — `LoanDelinquencyService` never scheduled.** PAR/delinquency data is stale unless invoked; AI portfolio answers would quote stale statuses. **Fix:** schedule `updateDelinquencyStatuses(orgId)` (per-org job) before Phase 11.7.
7. **R7 — No production path sets `LoanStatus::Completed`.** Lifecycle/eligibility "active loans" and predictions depend on correct terminal statuses. Verify whether completion is handled elsewhere; if truly absent, add via `LoanRepaymentService` when schedule fully paid, as an authorized human-in-the-loop state transition.
8. **R8 — `RepaymentNumberGenerator` lacks `lockForUpdate`** (minor race under concurrent collections). Mitigate before heavy AI-adjacent automations increase throughput.
9. **R9 — Financial answers must read stored columns/report services only.** No model-side computed balances exist; AI must not sum transactions. Already enforced by tool design; keep as a test assertion (financial correctness §23).
10. **R10 — No API routes.** Chat is best served as web JSON under existing session/auth; avoid introducing a public token-guard API for AI (keeps CSRF + session protections).
11. **R11 — Test baseline gap.** Before any AI phase, capture a green baseline (`composer test`) and add the tenant matrix, RBAC, and audit tests listed in §23; the current suite is strong but has no AI-related protections to anchor on.
12. **R12 — Provider fallback / offline behaviour.** Chroma of connectivity in Tanzania: the app should degrade to "AI unavailable; use the existing pages" rather than fail hard — scope outage handling in the `AiProviderService` contract and UI in 11.4.

---

## §23 — DEDICATED TESTING STRATEGY (owned by phases above)

| Area | Concrete tests |
|---|---|
| Authorization | Member persona cannot read another member (same org + cross org); Org A admin denied org B (after R1); Branch A manager denied branch B; Super Admin stays privileged. |
| Tool security | Each tool: `ok` for role+scope; `forbidden` for wrong role; `forbidden` for wrong tenant; wrong-type ids rejected; tool names not accepted from model output. |
| Financial correctness | AI summary equals `FinancialStatementService::getMemberFinancialSummary`; `get_trial_balance` equals `TrialBalanceService::generate`; eligibility answer equals `EligibilityCheckResult`. |
| Hallucination control | Empty/no-data tool result → assistant returns "no data"; unauthorized → does not fabricate; numbers only from `source_references`. |
| Tenant isolation | org A query never touches org B rows at service+DB level (assert row counts); RAG search scoped by `organization_id`; knowledge tier precedence. |
| Audit | every tool attempt writes `ai_tool_calls` (including forbidden); `audit_logs` gets `ai.*` events with `organization_id`. |
| Prompt injection | "ignore previous instructions / system prompt" strings in user text and in knowledge chunks do not change tool behavior (guardrail tests). |

---

## §26 & §27 — COMPLIANCE CHECKLIST

- [x] Inspected repository, architecture, tests, reusable services, authorization boundaries, tenant boundaries.
- [x] AI is designed strictly as an intelligence layer over existing services (no Finance/Loan/Accounting/Guarantor/Collateral/RBAC/Audit rebuilds; no duplicate ledgers or eligibility engines).
- [x] No controllers, migrations, models, services, routes, views, AI API calls, vector DB, embeddings, chatbots, or ML models were created in this phase.
- [x] Phase 11.1 does **not** start until this audit is reviewed and approved.

---

*End of Phase 11.0 Architecture Audit.*