# Phase 11.7.1 — Public Landing-Page AI Assistant

**Start:** Phase 11.7 (`ai:chat-guest` commit)
**Scope:** an anonymous, session-anchored AI assistant on the public landing page (the pre-login `HomeController` index). Visitors get a single stable public conversation and can ask questions answered from a **public-only** knowledge scope. The authenticated chat surface (Phase 11.7) is strictly separated from it, the stateless guest endpoint remains untouched, and the whole surface is rate-limited, monotonically bounded in history/output, XSS-safe by construction, and cleanable by schedule.
**STOP rule honored:** no feedback/learning loop, no personalization, no member/tenant data exposure, no persistence of any member data, no new write/approval tools, no external web search. Phase 11.8 (predictive ML, credit scoring, forecasts, automated decisions/approvals) is not begun.

---

## 1. Status

**COMPLETE** — all Phase 11.7.1 requirements implemented, tested, and verified.

- New suites: `AiPublicChatTest` (12), `AiPublicConversationTest` (10), `AiPublicRagTest` (6) — 28 new tests.
- Run under `php -d max_execution_time=0 vendor/bin/phpunit tests/Feature/AI/AiPublic*.php`: each suite green twice; existing guest / chat / knowledge / tool-registry suites remain green (Pint + `php -l` clean over every changed file).

---

## 2. Separation of surfaces

| Surface | Route | Auth | Session anchor | Knowledge |
| --- | --- | --- | --- | --- |
| Authenticated chat (11.4/11.7) | `POST /ai/chat`, `GET /ai/conversations*` | member | `user_id` | tenant-scoped, `ai.knowledge.search` |
| Stateless guest chat (11.7) | `POST /ai/chat/guest` | none | stateless (`guest_id` arg, no session) | none — 503 if AI off |
| **Public landing assistant (11.7.1)** | `POST /ai/public/chat`, `GET /ai/public/conversations/current` | none | **server session only** | `visibility=public` documents |

- The public conversation is stored with `type = public` and **all tenant/user columns null** (`user_id`, `organization_id`, `branch_id`, `vicoba_group_id`). It can never be claimed by a member later and disappears from every authenticated listing.
- `AiConversation` gains a `type` enum (`AiConversationType::Private|Public`, default `private`) and a `uuid` column (nullable, unique) used purely as the session anchor. Existing private conversations are untouched by the migration.
- `AiConversationService::listForUser()` now only ever returns `private` conversations; public ones are invisible to the authenticated chat UI.
- `AiConversationService::createPublic($uuid)` + `findPublicByUuid($uuid)` implement public-only creation/lookup; a public conversation can be read/continued only via the public routes, which resolve identity exclusively from the session — never from a browser-supplied id (proven by test: a fresh visitor cannot enumerate or read an existing public conversation).

---

## 3. Session anchoring and the browser contract

- The browser only ever sends `{ "message": "..." }`. The server generates `Str::uuid()`, stores it as `ai_public_conversation_uuid` in the session, and creates the conversation the first time a visitor posts; all later posts and the `current` endpoint resolve the same conversation from the session.
- `GET /ai/public/conversations/current` restores the conversation (id + matched history) on page load, or returns a null conversation for a fresh visitor — the widget refreshes itself from it.
- **The public chat is opt-in for the browser page and guest-only:** the assistant renders on the landing page only when the visitor is a guest. A signed-in member on the landing page gets the ordinary authenticated widget instead (`@auth` / `@guest` branches in `layouts/landing.blade.php`).
- Two different visitors on the same machine/IP cannot read each other's conversations (isolated uuids), and a fresh session cannot see an old one even with a known id (id is never accepted from the client).

---

## 4. Public knowledge scope (public RAG)

A single new `AiKnowledgeScope::Public = 'public'` entry extends the Phase 11.5 knowledge model:

- Documents published with `visibility = public` keep `user_id` + all tenant columns null and are treated like Global for the authenticated path: `AiKnowledgePolicy` returns true for `Global` and `Public` visibility, so authorized staff can also search public documents with `ai.knowledge.search` (mirrors Global behavior, tested).
- `AiKnowledgeRetrievalService::searchPublic()` is the anonymous retrieval path. It never throws — any embedding/provider failure audits and returns `[]` — and its candidate set is `status = active AND visibility = public AND all tenant columns null`, filtered **before** similarity scoring, with the same server-side top-K bound (`ai.knowledge.top_k`). Draft/archived/failed public documents, and every non-public document (global, organization, branch, group), are never candidates (tested).
- Retrieval is audited via `ai.knowledge.retrieved` with `scope => 'public'`; no prompt, content, vector, or key is logged (Phase 11.5 audit hygiene carries over).
- The chat orchestration wraps results in the existing `<FINANCEPRO_KNOWLEDGE>` read-only block; empty retrieval injects nothing. Retrieved text is data, never a system directive.

---

## 5. Chat flow, bounds, and limits

`AiPublicChatOrchestrationService::answer()`:

1. Appends the user message; retrieves public context via `searchPublic` (never blocks the chat).
2. Builds a bounded message window — platform + `public_system_instructions` + the last `public_chat.max_history_messages` turns — so context cost is monotonically capped regardless of conversation age.
3. Calls the provider through the existing `AiProviderService` (satisfying `AiProviderInterface`), appends the assistant reply with usage, stores provider/model/status on the conversation, and audits `ai.provider.requested` under scope `public`. `AiUnavailableException` bubbles to the controller.
4. Output is clamped by `public_chat.max_output_tokens` (default 512) and `public_chat.max_message_length` (default 4000). Privilege-bearing keys (`organization_id`, `member_id`, `role`, `tenant_id`, …) are rejected by the request `prohibited` rule using the shared `AiToolPolicy::FORBIDDEN_ARGUMENT_KEYS`.

`AiPublicChatController`:

- Gate: `ai.public_chat.enabled` && overall AI enabled && provider available → else controlled `503` with `AiUnavailableException::SAFE_MESSAGE` (no internals leaked; a test pins the same message as the authenticated 503 path).
- Time-budgeted like the authenticated controller (`set_time_limit(600)`).

**Rate limiting** (custom `RateLimiter::for('ai.public')` in `AppServiceProvider`):

- Bound is `rate_limit`/minute per **(source IP, and additionally the session-anchored public conversation uuid)**. The uuid is server-generated, so it is the visitor's stable per-session identity — a shared office IP cannot drain a single budget for everyone on it, one visitor cannot silently reset the cap by rotating IPs, and a brand-new visitor (no uuid yet) still has the IP baseline. The uuid used for keying is the one stored in the session, mirroring the request path exactly.
- On exhaustion the middleware responds with a controlled JSON 429: `"You're sending messages too quickly. Please wait a moment and try again."` (verified against `Limit::response` semantics in Laravel 12.61).

---

## 6. The landing widget (`partials/public-ai-chat.blade.php`)

- Renders **only** for guests with AI + public chat enabled; otherwise the landing page carries no assistant markup.
- Sentinels: a single-bound guard `window.__financeProPublicAiChatBound` prevents double-binding; `id="aiPublicWidget"` used in tests to assert the presence.
- **No `innerHTML` anywhere in the page** — AI output is injected via `textContent` on a `div`, so hostile reply/echo content cannot execute (a regression test asserts the markup never contains the string `innerHTML`, matching the convention from the authenticated chat UI).
- On load it GETs `current`, renders the restored history as inert text, then POSTs new messages; suggestions offer sample questions. Escaping is enforced by rendering assistant text through `textContent` only.
- CSRF is already present in `layouts/landing.blade.php` as the `<meta name="csrf-token">` used by the fetch calls.

---

## 7. Seeder and lifecycle cleanup

- `AiPublicKnowledgeSeeder` (registered last in `DatabaseSeeder`) stores seven public documents describing FinancePro, VICOBA, loans, savings/shares/welfare, getting started, and contact (`info@financepro.co.tz`), all `scope = Public`, `source = public-knowledge`, creator = first Super Administrator or first user. It forces the sync queue so seeds complete inline; ingestion dedupes by checksum, so re-running is idempotent.
- `AiCleanupPublicConversations` (`php artisan ai:cleanup-public-conversations`) deletes `type = public` conversations older than `public_chat.retention_days` in `chunkById(200)` batches; scheduled daily at 02:00 in `routes/console.php`. It can never touch private conversations.

---

## 8. Configuration

`config/ai.php` → `ai.public_chat`: `enabled` (true), `rate_limit` (10/min), `max_message_length` (4000), `max_history_messages` (8), `max_output_tokens` (512), `retention_days` (30); plus `ai.public_system_instructions`. `.env.example` exposes `AI_PUBLIC_CHAT_ENABLED`, `AI_PUBLIC_CHAT_RATE_LIMIT`, `AI_PUBLIC_CHAT_MAX_MESSAGE_LENGTH`, `AI_PUBLIC_CHAT_MAX_HISTORY_MESSAGES`, `AI_PUBLIC_CHAT_MAX_OUTPUT_TOKENS`, `AI_PUBLIC_CHAT_RETENTION_DAYS`, and commented `AI_PUBLIC_SYSTEM_INSTRUCTIONS`.

---

## 9. Tool registry

`AiToolRegistry` gains two **public** capability entries, both `NullTool` (harmless, non-readable at runtime) with permissions `[]`: `ai.public.chat` and `ai.public.knowledge.search`, under a new `SCOPE_PUBLIC` constant. `businessCapabilities()` count is unchanged (NullTool entries are filtered), and `publicCapabilities()` is the discoverable route registry the controller checks against `config` before answering. Registered private capabilities are never reachable from the public surface.

---

## 10. Security review

- Public rows are structurally unclaimable: type `public` is excluded from every private listing/read/continue path (asserted), and the public controller injects no tenant/user ids.
- Identity is never trustable from the client: only the session uuid exists, bearer-provided values are rejected by `prohibited`.
- Query building uses the same scope-filter-before-scoring pattern as Phase 11.5; no `DB::raw`/`whereRaw` added.
- The 503/429 failure messages are constant safe strings; the rate limiter key never includes raw client input beyond keyed uuid; no credentials or prompts are logged.

---

## 11. Tests

New: `tests/Feature/AI/AiPublicChatTest.php` (12), `AiPublicConversationTest.php` (10), `AiPublicRagTest.php` (6). Highlights:

- **Chat:** happy-path reply shape and 2 messages stored; anonymous tenant-free conversation; message required + configured-length rule; privilege keys rejected with zero conversations created; hostile script stored verbatim as data only (never rendered as markup); 503 when AI disabled and when the public switch is off; rate-limit 429 with the friendly message; landing page renders the widget without `innerHTML` and hides it when disabled; private capabilities never surface on the public path.
- **Conversation:** one stable conversation across consecutive messages; `current` restores conversation + history, returns empty for a fresh visitor, and honors only the session (a supplied uuid cannot read another session's conversation); fresh-vs-seeded sessions isolated; public conversations never listed or readable through `/ai/conversations`, never continuable through `POST /ai/chat`, and existing private chats never reclassified.
- **RAG:** public doc retrieved by `searchPublic`; global/org/branch/group docs never returned; draft/archived public docs excluded even with matching chunks; authenticated authorized staff see public docs through the ordinary search; empty with no public docs and for blank queries.

---

## 12. Stop boundary

Phase 11.7.1 stops here. No personalization, no member data in any public row, no anonymous access to tenant knowledge, no new mutating tools, no ML-based identity or content inference. The stateless guest endpoint is untouched; public chat never shares storage semantics with authenticated chat. Phase 11.8 does not begin without a new instruction.