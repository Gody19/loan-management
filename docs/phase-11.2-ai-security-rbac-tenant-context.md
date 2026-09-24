# Phase 11.2 — AI Security, RBAC & Tenant Context

**Start:** after Phase 11.1 commit `4f273d9`
**Scope:** security boundary for the Phase 11.1 AI foundation — trusted context, default-deny capability authorization, tenant revalidation, auditing.
**STOP rule honored:** no business-data AI tools, no RAG/vector search, no model training, no chat UI were implemented.

---

## 1. Status

**COMPLETE** — all Phase 11.2 requirements implemented, tested, and re-verified.

Two consecutive full-regression runs are green (`Tests: 976 passed (2196 assertions)`, `0 failed`, exit code `0`).

---

## 2. Existing Security Architecture Inspected

Phase 11.2 builds on (and never duplicates) the existing infrastructure:

- **Spatie permissions** + `permission:` route middleware (`CheckPermission`) — guests redirect to login; missing permission → 403.
- **`Gate::before`** in `app/Providers/AuthServiceProvider.php:109` — grants Super Administrator implicitly for policy checks (platform-level convenience, not used by the AI boundary).
- **`app/Services/OrganizationContext.php`** — authoritative org resolution (`getUserOrganizationIds`, `authorizeOrganization`, `modelBelongsToUserOrganization`). Reused by `AiContextBuilderService` so there is exactly one tenant-truth source.
- **`app/Services/AuditService.php`** + `audit_logs` — single audit pipeline reused for `ai.authorization.allowed` / `ai.authorization.denied`.
- **`MemberPolicy` role ladder** (Super Administrator → Organization Administrator → Branch Manager → default) — studied as the project’s policy convention.
- **Tenant-enforcement pattern** across services/requests (no second tenant system introduced).

---

## 3. New Security Components

| Component | Responsibility |
|---|---|
| `app/AI/DTOs/AiContextData.php` | Immutable, trusted execution context. Outputs-of-request never contribute to it. |
| `app/AI/Services/AiContextBuilderService.php` | Builds context from authoritative state: Spatie `getRoleNames`, `getAllPermissions`, `OrganizationContext::getUserOrganizationIds`, `user->branches()`, the user's own `Member` record. Super Administrator gets an **explicit** broader platform set (all org/branch/vicoba ids) — never `skipAllSecurity()`. |
| `app/AI/Services/AiToolRegistry.php` | Explicit capability whitelist. Each capability declares required permissions and an exact argument schema. No dynamic discovery; model output can never name a class/method/SQL/callable. |
| `app/AI/Policies/AiToolPolicy.php` | Default-deny evaluation: unknown/malformed capability → deny; `FORBIDDEN_ARGUMENT_KEYS` (organization_id, branch_id, vicoba_group_id, member_id, user_id, role, permission, is_super_admin, scope, sql, query, file, path, command, class, method, callable, ...) → deny; unknown argument → deny; type mismatch → deny; permission gate → deny when absent. |
| `app/AI/Policies/AiAuthorizationCategory.php` | Structured, safe denial reasons for auditing (no prompts/secrets). |
| `app/AI/Exceptions/AiAuthorizationException.php` | 403 (HttpExceptionInterface), generic safe message, structured category/capability/metadata. |
| `app/AI/Services/AiGuardrailService.php` | Enforces: feature enabled (else 503 unavailable), authenticated (else 403 + audit), policy default-deny, escalation rejection, conversation access revalidation from context (super = platform; owner ok; **VICOBA Member = owner-only**; staff = org membership), and audits every decision. |
| Config `ai.system_instructions` (+`AI_SYSTEM_INSTRUCTIONS`) | Security-oriented system prompt guidance. **Guidance only** — Laravel enforcement is the real boundary. Never persisted as a conversation message. |

### Wiring
- `AiController` passes every request (list/read/chat) through `AiGuardrailService::authorize(...)`; `show`/`store` additionally revalidate conversation ownership/tenant via `checkConversationAccess`.
- `POST /ai/chat` rejects any privilege-influencing request key with `prohibited` validation (built from `AiToolPolicy::FORBIDDEN_ARGUMENT_KEYS`) → 422 before the guardrail runs.
- `AiConversationService` — VICOBA Members see/access **only their own** conversations (list + authorizeAccess); `send()` prepends the configured system instruction to the provider request without persisting it.
- `RolePermissionSeeder` grants `ai.view`/`ai.use` to all ten staff/member roles (scope still enforced by context; super admin already holds all `ai.*` via `syncPermissions(Permission::all())`).

---

## 4. AI Permission Matrix (documented, no blind grants)

| Capability | Required permission | Grant via seeder |
|---|---|---|
| `ai.conversation.list` | `ai.view` | all roles + Super Administrator |
| `ai.conversation.read` | `ai.view` | all roles + Super Administrator |
| `ai.chat` | `ai.use` | all roles + Super Administrator |

Every role is scoped by its trusted context (own conversations / own organizations). VICOBA Members are owner-only on every path (service, guardrail, HTTP). Super Administrator remains capability- and permission-gated: `AiToolPolicy` does **not** short-circuit for the super role.

**Not granted to any role:** no `ai.*` permission beyond `ai.view`/`ai.use`; **no financial-data capability exists yet** (Phase 11.3 gate).

## 5. Tenant Model

- **Organization:** context `organizationIds` = `OrganizationContext` membership (Super Administrator = all). New conversations get the user's org only when their context has exactly one org; conversation access checks membership in context arrays.
- **Branch:** context `branchIds` = `$user->branches()` authoritative assignments (Super Administrator = all). READY for Phase 11.3 tools.
- **Member:** context `memberId` = the user's own `Member` record only (`$user->member?->id`). `member_id` is a forbidden request key.
- **VICOBA Group:** non-super users receive an empty `vicobaGroupIds` (no authoritative user→group table); platform scope for Super Administrators only.
- Client can never supply `organization_id`, `branch_id`, `member_id`, `vicoba_group_id`, `user_id`, roles, permissions, or `scope`.

---

## 6. Security Tests

`tests/Feature/AI/AiContextTest.php`, `tests/Unit/AiToolPolicyTest.php`, `tests/Feature/AI/AiGuardrailTest.php`, `tests/Feature/AI/AiEndpointSecurityTest.php` (+ existing `AiAuthorizationTest`, `AiConversationTest`, `AiProviderTest`, `AiConfigurationTest`):

- **Authn (guest → 401/redirect, unauthenticated authorize → 403 + audit)**
- **Permission gate (403 for missing `ai.*`)** and **capability default-deny (`ai.financial.*` → denied)**
- **Role matrix**: super admin still gated by registration + permission; staff org-member allowed; non-member denied
- **Tenant**: conversation A(org A) denied to user in org B over HTTP (list/read/chat); prompt cannot redirect or re-scope
- **Escalation**: 16 forbidden argument keys rejected in policy + 11 keys rejected at HTTP validation (422) with no conversation created
- **Prompt injection**: scope stays fixed to trusted context; foreign membership never granted; conversation rebuild to another id rejected
- **Arbitrary execution**: model output treated as data only (stored as `assistant` message; no `ai.tool.executed` event; no dynamic call path exists)
- **Conversation IDOR**: User A→B, Org A→B, and Member A→B all denied; member owner-only listing
- **Audit integrity**: `ai.authorization.allowed` / `ai.authorization.denied` written with safe metadata; no prompt/secret content present
- **System instructions**: prepended to provider payload but never persisted; no `system` row in `ai_messages`
- All tests offline via `FakeAiProvider`/config (no credentials, no network).

## 7. Regression

- **Before (Phase 11.1):** `931 passed / 0 failed / 2043 assertions`
- **After (Phase 11.2):** `976 passed (2196 assertions), 0 failed` — two consecutive full runs, exit code 0.
- No existing test deleted or weakened; +45 tests, +153 assertions.

## 8. Security Findings (§30 self-review)

- `Gate::before` present only in `AuthServiceProvider` (Super Administrator) — untouched, not used by the AI boundary.
- `can(...)`/`authorize(...)` across policies all route through Spatie permissions + established role ladders; no new bypasses introduced.
- `hasRole` usage: AI code uses it only to apply **stricter** scoping (VICOBA Member owner-only; Super Administrator platform context) — never to grant AI authority.
- **No dynamic execution primitives** (`call_user_func`, `eval`, `exec`, `shell_exec`, `passthru`, `system`) anywhere in `app/` — zero matches.
- `DB::`, `withoutGlobalScopes` exist only in pre-existing finance services/number generators (transactional + locking), all server-side; unchanged by Phase 11.2.
- Request-derived tenant ids in existing business controllers are always wrapped in `OrganizationContext::authorizeOrganization` or permission-scoped query filters (pre-existing pattern, unchanged).
- `AiController` derives org/branch/member **only from the trusted context**, not from the request; forbidden keys are co-rejected by validation and policy.
- Audit events carry capability/category/argument-keys — never prompts, provider output, or secrets.

No new findings requiring action. Remaining exposure is intentional and gated for Phase 11.3.

## 9. Phase 11.3 Gate

**READY** (not executed). Governance READY for adding business tools only through `AiToolRegistry` entries that pair **permissions + argument schemas + tenant-scoped handlers** and consult this context. No business-data tool, RAG, model training, or chat UI was added in Phase 11.2.