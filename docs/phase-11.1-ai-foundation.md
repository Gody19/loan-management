# PHASE 11.1 — AI FOUNDATION

**Status:** Implemented and verified — full regression runs green across two consecutive executions. **STOP (no Phase 11.2 work was started).**
**Project:** FinancePro — Tanzania VICOBA & Microfinance Management Platform
**Stack:** Laravel 12 / PHP 8.2 / MySQL 8 / SQLite (`:memory:` test env) / Spatie Laravel Permission
**Date:** 2026-09-24
**Rules honored:** AI is an opt-in, provider-independent foundation with no business-domain query paths, no RAG/embeddings/vector search, no ML/predictive tooling, no chat UI, no public API, and no live provider in tests (all provider calls are `Http::fake` / the offline `FakeAiProvider`). Existing VICOBA/loan/finance/accounting/RBAC/tenant behavior is unchanged; the only seeder change is additive (`ai.*` permissions). No AI outputs are ever executed (no generated SQL/methods).

---

## 0. TEST BASELINE & GATE

| Metric | Before (Phase 10.9.1 baseline) | After Phase 11.1 |
|---|---|---|
| Test executions per full run | 882 | **931** |
| Failed | 0 | **0** |
| Assertions | 1916 | **2043** |

Delta: **+49 tests / +127 assertions** (the new `tests/Feature/AI` suite). Two consecutive full-suite runs both green (`931 passed / 2043 assertions, 0 failed`). No test was skipped, deleted, or weakened. Targeted AI suite: `49 passed (127 assertions)`.

---

## 1. WHAT WAS BUILT (SCOPE-CLOSED)

Everything below lives behind the `config/ai.php` enabled switch (default `false`). When disabled, every AI route returns an explicit 503 payload and the app never touches a provider.

### 1.1 Configuration & env
- `config/ai.php`: `enabled`, `default_provider` (`openai`), `default_model`, `timeout`, `temperature`, `max_output_tokens`, `max_history_messages`, and a per-provider `providers` map. Credentials come exclusively from env.
- `.env.example`: added `AI_ENABLED`, `AI_PROVIDER`, `AI_MODEL`, `AI_TIMEOUT`, `AI_TEMPERATURE`, `AI_MAX_OUTPUT_TOKENS`, `AI_MAX_HISTORY_MESSAGES`, `OPENAI_API_KEY`, `OPENAI_BASE_URL`. `.env` itself untouched.

### 1.2 Core structure (`app/AI/`)
- `Contracts/AiProviderInterface` — the only boundary FinancePro code talks to: `name(): string` + `generate(AiRequestData): AiResponseData`.
- `DTOs/` — `AiMessageData(role, content)`, `AiRequestData(messages, model, temperature?, maxOutputTokens?, metadata, conversationId?)`, `AiResponseData(provider, model, content, inputTokens?, outputTokens?, totalTokens?, finishReason?, providerRequestId?, metadata)`.
- `Exceptions/` — `AiProviderException` (sanitized, internal) and `AiUnavailableException` (implements `HttpExceptionInterface`, renders 503, carries only the fixed safe copy: *"AI service is currently unavailable. Please use the existing FinancePro features."*).
- `Providers/FakeAiProvider` — deterministic, offline, no credentials; used by tests and as a safe fallback.
- `Providers/OpenAiProvider` — OpenAI Chat Completions via Laravel's `Http` facade (no SDK). Builds payload, maps HTTP failures to sanitized categories (401/403 auth, 408/429 rate-limit, 502/503/504 unavailable, malformed payload rejected). Credentials never leave the class.
- `Services/AiProviderService` — resolves providers from config, enforces the enabled switch + credential presence, and normalizes every failure to a single `AiUnavailableException`.
- `Services/AiConversationService` — conversation lifecycle with ownership + tenant enforcement on every retrieval. **Performs no business-domain queries** (no `Member`/`Loan`/`SavingsAccount`/`JournalEntry`/...). Bounds history by `ai.max_history_messages`, records provider usage on messages, and integrates with the existing `AuditService`.

### 1.3 Persistence
- Migrations `2026_09_24_000001/2/3`: `ai_conversations` (nullable `user_id`/`organization_id`/`branch_id`/`vicoba_group_id` with `nullOnDelete`, `status`, `provider`, `model`), `ai_messages` (FK `ai_conversation_id` `cascadeOnDelete`, `role`, `content`, usage columns, `metadata` json), `ai_model_versions` (`unique(provider, model)`)).
- Models `AiConversation`, `AiMessage`, `AiModelVersion` (+ factories) with enum casts and query scopes.

### 1.4 Endpoints (minimal, internal, JSON-only, no chat UI)
Under `auth` + `suspended`, within the `ai.*` permission:
- `GET  /ai/conversations` (`permission:ai.view`)
- `GET  /ai/conversations/{conversation}` (`permission:ai.view`)
- `POST /ai/chat` (`permission:ai.use`) — persists user message, calls provider, persists assistant reply + usage, returns `conversation_id/provider/model/content/usage`; 503 JSON when unavailable.

### 1.5 RBAC & audit
- `RolePermissionSeeder`: added `'ai'` to `$modules` — grants the full `ai.*` set only to **Super Administrator** (no other role's permission matrix changes) plus an explicit `ai.use` permission (see §3 deviation).
- Audit events via existing `AuditService`: `ai.conversation.created`, `ai.message.created`, `ai.provider.requested`, `ai.conversation.closed`. **Prompt/reply content and API keys are never written to audit logs or server logs.**

### 1.6 Tenant & ownership isolation
Enforced at both layers:
- Service layer: `findForUser` / `listForUser` gate by `user_id` OR membership in the conversation's `organization_id` via the existing `OrganizationContext`, with Super Administrator short-circuit.
- HTTP layer: cross-organization conversations return 403 (`AiAuthorizationTest::test_cross_organization_conversation_is_denied_over_http`).

---

## 2. TEST SUITE (`tests/Feature/AI`, 49 tests / 127 assertions — all offline)

| Class | Coverage |
|---|---|
| `AiConfigurationTest` | default provider = openai; disabled by default; fake vs openai availability incl. missing credentials; unknown provider; history bound |
| `AiDtoTest` | DTO defaults/nullability; enum value sets |
| `AiProviderTest` | FakeAiProvider determinism; OpenAI success mapping; auth header + payload assertions via `Http::assertSent`; 401/429/503/timeout/malformed/missing-key sanitization; service wraps failure into 503-safe `AiUnavailableException`; generation refused when disabled |
| `AiConversationTest` | create/audit; owner vs intruder retrieval; cross-org isolation; super-admin access; list scoping; append + audit-without-content; bounded history; prune; full send loop with fake provider; send-while-disabled; archive/close |
| `AiMessageTest` | enum casts; cascade delete; integer usage casts |
| `AiModelVersionTest` | unique(provider, model); status/provider scopes |
| `AiAuthorizationTest` | guest redirect/401; 403 without permission; list with `ai.view`; super-admin chat; 503 when disabled (both GET and POST with the safe message); 422 validation; cross-org 403 over HTTP; **app remains fully functional when AI is disabled** (`/dashboard` 200 while `/ai/chat` 503) |

---

## 3. DOCUMENTED DECISIONS & DEVIATIONS

1. **`ai.use` permission** — the shared seeder action list (`view, create, update, delete, assign, approve, export, import, post, reverse, manage`) has no `use`. To keep the chat gate `permission:ai.use` without altering any other module's action matrix (which would implicitly expand e.g. Organization Administrator permissions), `ai.use` is created **explicitly and only for the `ai` module** (`RolePermissionSeeder`, after the module loop).
2. **Permission granularity is intentionally minimal**: only Super Administrator receives `ai.*`. The full role matrix is deliberately deferred to Phase 11.2 (per plan).
3. **No business-data exposure**: conversation metadata records only identity/usage, never member/loan/financial data; `AiConversationService` has no business-model queries; `AiMessage` content is stored but never surfaced through any existing FinancePro feature.
4. **Provider-independent**: swapping in another provider is a new class implementing `AiProviderInterface` + a `config/ai.php` entry; controllers unaware of concrete providers.

---

## 4. STABILITY VERIFICATION LOG

| Run | Result |
|---|---|
| AI suite (new) | 49 passed / 0 failed / 127 assertions |
| Full suite #1 | 931 passed / 0 failed / 2043 assertions |
| Full suite #2 | 931 passed / 0 failed / 2043 assertions |

---

## 5. FILES CHANGED / ADDED

**New — app:** `config/ai.php`, `app/AI/Contracts/AiProviderInterface.php`, `app/AI/DTOs/{AiMessageData,AiRequestData,AiResponseData}.php`, `app/AI/Exceptions/{AiProviderException,AiUnavailableException}.php`, `app/AI/Providers/{FakeAiProvider,OpenAiProvider}.php`, `app/AI/Services/{AiProviderService,AiConversationService}.php`, `app/Enums/{AiMessageRole,AiConversationStatus,AiModelVersionStatus}.php`, `app/Models/{AiConversation,AiMessage,AiModelVersion}.php`, `app/Http/Controllers/AiController.php`.
**New — data/tests:** migrations `database/migrations/2026_09_24_000001/2/3`; `database/factories/{AiConversation,AiMessage,AiModelVersion}Factory.php`; `tests/Feature/AI/{AiTestCase,AiConfigurationTest,AiDtoTest,AiProviderTest,AiConversationTest,AiMessageTest,AiModelVersionTest,AiAuthorizationTest}.php`.
**Modified:** `config` unchanged; `.env.example` (+AI vars), `database/seeders/RolePermissionSeeder.php` (`'ai'` module + explicit `ai.use`), `routes/web.php` (3 AI routes), `tests/TestCase` unchanged.

Existing FinanceProduct suites ran **unmodified** and green (e.g. `UserManagement\PermissionTest`, `UserManagement\RoleTest`, `UserManagement\UserTest`, `VicobaGroupTest` — 0 failures, confirming the seeder change broke nothing; `PermissionService` groups dynamically by current DB state).

---

## 6. REMAINING RISKS (documented, out of scope for Phase 11.1)
1. Conversation history is stored content that, in production, will require data-retention/redaction policy (deferred).
2. Only OpenAI + the offline fake are wired; other providers are an interface implementation away.
3. `AiController` JSON endpoints are minimal; a Phase 11.2 chat UI/admin UX and the full `ai.*` role matrix remain unimplemented by design.

---

**Result: `Tests: 931 passed (2043 assertions), 0 failed` — stable across 2 consecutive full runs. AI remains disabled by default (`AI_ENABLED=false`) so production behavior is unchanged. STOP per Phase 11.1 gate.**