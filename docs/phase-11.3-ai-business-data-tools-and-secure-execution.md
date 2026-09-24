# Phase 11.3 — AI Business Data Tools & Secure Tool Execution

**Start:** after Phase 11.2 commit `9d38b34`
**Scope:** first read-only AI business-data tools (member, financial, savings/share/welfare, loans, repayments, applications, eligibility, guarantor, collateral) behind the Phase 11.2 security boundary, with the AI explaining only authoritative FinancePro data.
**STOP rule honored:** no write/approval/disbursement/repayment-post/reversal tools, no member-user CRUD, no role/permission changes, no raw SQL or dynamic execution from model output, no RAG/vector search/embeddings, no chat UI.

---

## 1. Status

**COMPLETE** — all Phase 11.3 requirements implemented, tested, and re-verified.

Two consecutive full-regression runs are green (`Tests: 1014 passed (2574 assertions)`, `0 failed`, exit code `0`).

---

## 2. Existing Architecture Inspected (no duplication)

Phase 11.3 reuses the authoritative FinancePro services and policies verbatim:

- `app/Services/FinancialStatementService.php` — `getMemberFinancialSummary` (savings/share/welfare aggregates from stored balances).
- `app/Services/LoanEligibilityService.php` — `checkEligibility` (member/plan active, amount+term ranges, active-loan 85% rule; **no savings-multiplier rule exists in FinancePro and none was invented**).
- `app/Services/GuarantorEligibilityService.php` — `getEligibility`/`canGuarantee`.
- `app/Services/CollateralRequirementService.php` — `getRequirement`/`resolveRule`.
- `app/Services/LoanDelinquencyService.php` — `getDaysPastDue` (authoritative delinquency).
- `app/Models/*` stored balances (`outstanding_balance`, `amount_paid`, `current_balance`, ...) — the single source of financial truth.
- Existing policies (`MemberPolicy`, `LoanPolicy`, `LoanApplicationPolicy`, `LoanPlanPolicy`) — delegated to for staff scope so the AI path never re-implements or loosens established authorization rules.
- `AiContextData` / `AiContextBuilderService` / `AiGuardrailService` / `AiToolPolicy` / `AiConversationService` / `AiAuthorizationException` — reused unchanged as the security boundary.

---

## 3. New Components

| Component | Responsibility |
|---|---|
| `app/AI/Contracts/AiToolInterface.php` | All business tool adapters implement it (`execute(User, AiContextData, array): array`). |
| `app/AI/Exceptions/AiToolException.php` | Safe structured tool failures: `unauthorized`, `not_found`, `validation_failed`, `business_rule`, `service_unavailable`, `internal_error`. `not_found`/`unauthorized` collapse to the same 403 message (no record-existence disclosure). |
| `app/AI/Services/AiToolRunnerService.php` | Executes a registered capability by **class-string from the registry constant** through `app($handlerClass)`; `is_a` guards; audits `ai.tool.requested` / `ai.tool.completed` / `ai.tool.denied` / `ai.tool.failed` with safe metadata. Never executes model output. |
| `app/AI/Services/AiToolAccessService.php` | Resolves records inside the acting user's scope only: `member_number`, `loan_number`, `application_number`, `loan_plan_id` are resolved tenant-scoped and policy-checked. **VICOBA Member = owner-only everywhere.** Dates validated (`validateDateRange`), limits bounded (`boundedLimit`). |
| `app/AI/Tools/NullTool.php` | Placeholder for conversation capabilities (`ai.chat`/`ai.conversation.*`); throws `internal_error` if ever executed as a business tool. |
| 11 DTOs (`app/AI/DTOs/`) | Typed, read-only presentation of authoritative data (`MemberSummaryData`, `MemberFinancialSummaryData`, `SavingsSummaryData`, `ShareSummaryData`, `WelfareSummaryData`, `LoanSummaryData`, `LoanRepaymentsData`, `LoanApplicationData`, `LoanEligibilityData`, `GuarantorEligibilityData`, `CollateralRequirementData`). |
| 12 tool adapters (`app/AI/Tools/`) | See matrix below. Financial figures are passed through from the authoritative services/models; the AI may restate but never recompute. |
| `AiHttp\Controllers\AiController::tool()` + `POST /ai/tool` | Endpoint pipeline (below). Route name `ai.tool`, middleware `permission:ai.use`. |
| `AiToolRegistry` (rewritten) | 15 capabilities (3 conversation + 12 business), each declaring `permissions` + `scope` + exact `arguments` schema + `handler` class-string + `description`. Conversation capabilities use `NullTool`; `businessCapabilities()` filters them out. |
| `AiToolPolicy` (+`float` rule) | Schemas may now use `string`/`integer`/`float`; `isIntegerLike`/`isFloatLike` are strict safe type checkers. `FORBIDDEN_ARGUMENT_KEYS` unchanged. |
| `RolePermissionSeeder` | 5 new permissions (`ai.member.view`, `ai.loan.view`, `ai.loan-repayments.view`, `ai.loan-application.view`, `ai.loan-eligibility.view`) created before the `$allPermissions` snapshot; explicit `$aiBusinessGrants` matrix per role. |
| `config/ai.php` | System instructions extended: restate authoritative tool results faithfully, distinguish posted vs reversed, never recompute or invent figures. |

### Endpoint pipeline (`POST /ai/tool`)

1. AI enabled? else controlled 503.
2. Validation: `capability` (string), `question` (required, ≤4000), `conversation_id` (nullable int); every `FORBIDDEN_ARGUMENT_KEYS` entry is **prohibited inside `arguments`** (422 before any other logic).
3. `AiGuardrailService::authorize(capability, arguments, user)` — default-deny policy, strict argument-schema/type check, permission gate, decision audited.
4. Optional `conversation_id` → `findForUser` (ownership/tenant enforced) + `checkConversationAccess`; else a conversation is created (single-org context → org-scoped; no `system` message ever persists).
5. `AiToolRunnerService::run` executes the registered handler; result is injected into the provider request as a **System message (never persisted)**; the user's question and the assistant answer are persisted.
6. `AiToolException` → `{message, category}` with `403/422/503/500`.

---

## 4. Capability → Permission Matrix (documented, no blind grants)

| Capability | Required permission | Seeded role grants |
|---|---|---|
| `ai.member.view`, `ai.member.financial_summary`, `ai.member.savings_summary`, `ai.member.share_summary`, `ai.member.welfare_summary` | `ai.member.view` | Org Admin, Branch Manager, Loan Officer, Credit Officer, Collection Officer, Secretary, VICOBA Member*, Super Administrator |
| `ai.member.loans`, `ai.loan.view` | `ai.loan.view` | Org Admin, Branch Manager, Loan Officer, Credit Officer, Collection Officer, VICOBA Member*, Super Administrator |
| `ai.loan.repayments` | `ai.loan-repayments.view` | Org Admin, Branch Manager, Loan Officer, Credit Officer, Collection Officer, VICOBA Member*, Super Administrator |
| `ai.loan.application.view` | `ai.loan-application.view` | Org Admin, Branch Manager, Loan Officer, Credit Officer, VICOBA Member*, Super Administrator |
| `ai.loan.eligibility.check` | `ai.loan-eligibility.view` | Org Admin, Branch Manager, Loan Officer, Credit Officer, VICOBA Member*, Super Administrator |
| `ai.guarantor.eligibility.check`, `ai.collateral.requirement.check` | `ai.loan-application.view` | Org Admin, Branch Manager, Loan Officer, Credit Officer, VICOBA Member*, Super Administrator |

\* **VICOBA Member = owner-only at runtime** (`AiToolAccessService::isOwnerOnly`): the member can only ever read/check **their own** member record, loans, repayments, applications, and eligibility. The permission grant alone is never sufficient — the trusted context's `memberId` gates every resolution.

**Not granted:** Treasurer, Accountant, Auditor. Super Administrator holds all five via `syncPermissions(Permission::all())` and remains capability- and permission-gated by `AiToolPolicy` (no super-role short-circuit).

### Tool catalog (arguments + server-side bounds)

| Capability | Arguments (schema) | Bound |
|---|---|---|
| `ai.member.view` | `member_number` | — |
| `ai.member.financial_summary` | `member_number` | — |
| `ai.member.savings_summary` | `member_number` | ≤50 accounts |
| `ai.member.share_summary` | `member_number` | ≤50 accounts |
| `ai.member.welfare_summary` | `member_number` | ≤50 accounts |
| `ai.member.loans` | `member_number`, `limit` | ≤25 |
| `ai.loan.view` | `loan_number` | — (adds authoritative `days_past_due`) |
| `ai.loan.repayments` | `loan_number`, `from`, `to`, `limit` | ≤50 |
| `ai.loan.application.view` | `application_number` | — (eligibility snapshot verbatim) |
| `ai.loan.eligibility.check` | `loan_plan_id` (int, required), `requested_amount` (float, required), `term_months` (int), `member_number` | — |
| `ai.guarantor.eligibility.check` | `member_number`, `application_number` | — |
| `ai.collateral.requirement.check` | `loan_plan_id` (int, required), `requested_amount` (float, required), `member_number` | — |

---

## 5. Identifier & Dataset Design

- **The model supplies business identifiers only** (`member_number`, `loan_number`, `application_number`, `loan_plan_id`). Tenant ids (`organization_id`, `branch_id`, `member_id`, `role`, `scope`, `sql`, ...) are rejected by both HTTP `prohibited` rules and `AiToolPolicy::FORBIDDEN_ARGUMENT_KEYS`.
- Records are resolved **only inside the trusted context's org set** (super = all) and re-checked through the existing policies; VICOBA Members additionally through their own `memberId`.
- **Loan completion/delinquency judged by stored balances** (`outstanding_balance`, `amount_paid`) and `LoanDelinquencyService` — never derived from installment counts.
- **Reversals shown distinctly**: `LoanRepaymentsData` reports `posted_count` / `reversed_count` and each row `is_reversed` + `reversal_reason`/`reversal_date`, so the AI cannot misstate a reversed payment as collected.
- **Eligibility** is exactly `LoanEligibilityService::checkEligibility` (savings-multiplier logic is **not** part of FinancePro and is not applied in the AI path — verified by test).
- **No PII**: NIDA, phones, emails, next-of-kin, addresses are never surfaced by any DTO.

---

## 6. Security Tests

New: `tests/Unit/AiToolRegistryTest.php`, `tests/Feature/AI/AiToolEndpointTest.php`, `tests/Feature/AI/AiToolFinancialTest.php`.

- **Registry integrity**: every business handler exists + implements `AiToolInterface`; unique handlers; descriptions present; schemas use only `string`/`integer`/`float`; no forbidden/tenant argument keys; class paths / SQL strings / callables are not capabilities.
- **Endpoint**: guest 401; missing `ai.use` 403; disabled AI → controlled 503; unknown capability → 403 default-deny; capability=class-string → 403 and **no `ai.tool.requested`**; 18 forbidden keys in `arguments` → 422 with no conversation; malformed float → policy 403 (`denied_key` audited); missing required arg → 422 `validation_failed` + `ai.tool.failed`; prompt injection cannot change scope; peer conversation reuse → 403; audit stores `argument_keys` only (never prompt/question/financial result); super admin queries any org's member through the same pipeline.
- **Financial parity**: `financial_summary`/`savings`/`share`/`welfare` totals == `FinancialStatementService`; `loan.view` balances == stored columns and `days_past_due` == `LoanDelinquencyService`; repayments distinguish posted/reversed and honor the date window; eligibility == `LoanEligibilityService` (incl. **not rescued by a huge savings balance**); guarantor == `GuarantorEligibilityService` (and the NIDA never leaks in JSON); collateral == `CollateralRequirementService`.
- **Isolation/IDOR**: staff in Org B cannot see Org A members/loans/repayments (`not_found`, 403, `ai.tool.denied`); VICOBA Members are owner-only for member, loan, and repayment tools; `member.loans` server-side bounded to 25 of 30.
- **PII sanitization**: `member.view` output couples only safe fields; national_id/phone/email/address never appear in the response.

### Implementation finding (resolved)

Laravel's `validated()` drops a parent array when wildcard `prohibited` child rules exist (they are still enforced). `POST /ai/tool` therefore reads `arguments` from the validated raw input and relies on `AiToolPolicy` to strictly re-enforce the capability schema (allowed keys, types, forbidden keys) before any tool runs — verified by tests.

---

## 7. Regression

- **Before (Phase 11.2):** `976 passed (2196 assertions), 0 failed`
- **After (Phase 11.3):** `1014 passed (2574 assertions), 0 failed` — two consecutive full runs, exit code 0.
- No existing test deleted or weakened; +38 tests, +378 assertions.

## 8. Security Findings (§30 self-review for this phase)

- **No dynamic execution in the AI path**: handlers are class-strings from the `AiToolRegistry` constant map; the runner uses `is_a(...)` and `app($handlerClass)`; model output can name a capability but never a class/method/SQL/callable. Zero matches for `call_user_func`, `eval(`, `shell_exec`, `passthru`, `proc_open`, pipeline `` ` `` across `app/AI`.
- **No raw SQL** from arguments in `app/AI`: zero matches for `DB::select/statement/raw/unprepared` / raw `DB::table(`.
- **No PII surface** in `app/AI`: zero matches for national_id/next_of_kin/phone/email/password/token handling in AI code.
- **`ai.tool.executed` does not exist** (asserted by test `model_output_is_never_executed_dynamically` and greped): execution events are `ai.tool.requested/completed/denied/failed`.
- **Read-only**: tools only call authoritative services/read models; no create/update/delete of business records; no approval/disbursement/post/reversal capability registered.
- All AI tests run offline via `FakeAiProvider`/config (no credentials, no network).

No new findings requiring action.

## 9. Phase 11.4 Gate

**NOT STARTED — STOP for human review.** Governance READY: future business capabilities may be added only through `AiToolRegistry` entries pairing **permission + argument schema + tenant-scoped handler**, consulted from the trusted context. Not implemented (and not attempted): chat UI, RAG/vector search, embeddings, write/approve/disburse/post/reverse tools, or any relaxation of the VICOBA owner-only scoping.