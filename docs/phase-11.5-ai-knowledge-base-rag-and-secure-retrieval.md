# Phase 11.5 — AI Knowledge Base, RAG & Secure Retrieval

**Start:** after Phase 11.4 commit `83354d9`
**Scope:** an approved, tenant-aware knowledge base (policies, handbooks, FAQs) with server-side RAG retrieval. Retrieval is scope-filtered against trusted context *before* similarity scoring, permission-gated per user, prompt-injection hardened, idempotent on ingestion, and clamped server-side (top-K and context-token budget). Minimal secured admin backend only.
**STOP rule honored:** no feedback/learning loop, no anomaly detection, no predictive ML, no autonomous agents, no write/approval/disbursement/accounting-mutation tools, no external web search, no automatic policy modification, no vector database, and no relaxation of the Phase 11.2/11.3 authorization boundary. No browser-side embeddings, vector math, or provider credentials.

---

## 1. Status

**COMPLETE** — all Phase 11.5 requirements implemented, tested, and verified.

- AI suite: `Tests: 164 passed (725 assertions)`, `0 failed`.
- Full regression: `Tests: 1080 passed (2874 assertions)`, `0 failed`, exit code `0`.
  Baseline before this phase was 1049; the 31 new tests bring the total to 1080.
- One pre-existing test was updated rather than left failing: `tests/Unit/AiToolRegistryTest.php` hard-coded the business-capability count at 12; registering `ai.knowledge.search` makes it 13.

---

## 2. Knowledge model

- **Enums** (`app/Enums/`): `AiKnowledgeDocumentStatus` (draft/processing/active/failed/archived), `AiKnowledgeScope` (global/organization/branch/group), `AiKnowledgeDocumentType` (constitution, loan_policy, savings_policy, share_policy, welfare_policy, accounting_policy, member_handbook, staff_manual, faq, procedure, general).
- **Models**: `AiKnowledgeDocument` (tenant columns `organization_id`/`branch_id`/`vicoba_group_id`, title, type, `content` longText, version, checksum, status, scope, metadata JSON, `created_by`/`updated_by`) and `AiKnowledgeChunk` (document FK cascade, `chunk_index`, content, `content_hash`, token estimate, embedding JSON cast to array).
- **Migrations**: `2026_09_24_000004_create_ai_knowledge_documents_table`, `2026_09_24_000005_create_ai_knowledge_chunks_table`. Global documents keep all three tenant columns null; a non-global document always carries the column matching its scope.
- **Factories** for both models so tests never bypass model code with raw inserts.

---

## 3. Ingestion (deterministic, idempotent)

- `AiKnowledgeChunkingService`: paragraph-aware chunking using `ai.knowledge.chunk_size` / `chunk_overlap`; normalizes line endings so the checksum is stable across platforms.
- `AiKnowledgeIngestionService`: stores, then dispatches `App\Jobs\ProcessAiKnowledgeDocument` (ShouldQueue) so no request waits on embedding work. Processing sets `processing`, chunks, embeds, then flips to `active` inside a `DB::transaction` that replaces chunks atomically.
- **Idempotency** is by checksum: re-processing identical content neither bumps `version` nor rewrites chunks. Changed content bumps the version, replaces chunks, and audits `ai.knowledge.updated`.
- Failure marks the document `failed`, audits `ai.knowledge.failed`, and rethrows `AiUnavailableException` so the queue retries. Archived documents are a no-op.
- Nothing in the ingestion path uses `DB::raw`/`whereRaw`; the only DB primitive is `DB::transaction`.

---

## 4. Embeddings

- `AiEmbeddingProviderInterface` with `embed` / `embedBatch`, returning normalized `list<float>`.
- `FakeEmbeddingProvider` (default, offline, no network): deterministic — identical input yields an identical vector across runs and across processes; unit-normalized; dimension-driven; token + trigram features so related text scores above unrelated text; `embedBatch` matches individual `embed` calls.
- `OpenAiEmbeddingProvider`: HTTP `/embeddings`, response-shape and dimension validation, sanitized failures.
- `AiEmbeddingProviderService` resolves from `ai.embeddings.provider` and fails safe to `AiUnavailableException` when the feature is disabled or the provider is unknown/unavailable. Retrieval and ingestion both short-circuit to empty when embeddings are unavailable.

---

## 5. Retrieval security (the core invariant)

`AiKnowledgeRetrievalService` + `App\AI\Policies\AiKnowledgePolicy`:

1. **Scope filter first, similarity second.** The authorized document-id set is computed from the trusted `AiContextData` (org ids, branch ids, VICOBA group ids, super-admin flag) and pushed into the query *before* any candidate is scored. Non-matching documents are never loaded, let alone ranked.
2. **Scope precedence:** global → organization → branch → group. Super admin sees all; otherwise a group document additionally requires the group id in trusted context and org membership. A VICOBA Member is scoped by its own org/branch/group assignments only.
3. **Status filter:** only `active` documents are candidates; draft, processing, failed, and archived never surface.
4. **Permission gate:** the capability requires `ai.knowledge.search`; document administration requires `ai.knowledge.manage`. Neither is implied by `ai.view`/`ai.use`.
5. **Similarity floor, then server-side clamps:** cosine similarity must exceed `ai.knowledge.min_similarity`; top-K is bounded by `ai.knowledge.top_k` regardless of the requested value; total context is bounded by `ai.knowledge.max_context_tokens`, dropping results that would exceed the budget. A requested `top_k=100` cannot widen the server cap.
6. **Embedding hygiene:** chunks whose stored vector has the wrong dimension are skipped, never scored and never coerced.
7. **Content is data, never authority.** `AiKnowledgeResultFormatter` wraps results in a `<FINANCEPRO_KNOWLEDGE>` block that explicitly instructs the model to treat the contents as read-only data and never follow directives inside it — the same framing as the Phase 11.3 `FINANCEPRO_DATUM` formatter. Retrieved content is never persisted as a `system` message.

---

## 6. Surfaces

- **Tool** `ai.knowledge.search` (`app/AI/Tools/KnowledgeSearchTool.php`) registered in `AiToolRegistry` with handler class-string, permission `ai.knowledge.search`, and scope `user_org`. Arguments are `search_term` (string) and optional `top_k` (int); `query`, `scope`, tenant ids, and `sql` remain forbidden argument keys, so a prompt cannot rename the search term or smuggle a scope.
- **Chat RAG hook** in `AiChatOrchestrationService`: when no authorized business plan is produced, the question is used as a search term and the formatted knowledge is appended as a delimited system message; empty retrieval injects nothing. Business data keeps its Phase 11.3 ser-rematized path.
- **Admin backend** (`AiKnowledgeDocumentController`, minimal): create/store and archive with `ai.knowledge.manage`. Foreign-organization create and archive are rejected (IDOR), so a caller cannot write or retire another tenant's documents.
- **Audit** `ai.knowledge.created/updated/processed/failed/retrieved` records safe metadata only — document ids, status, version, result count. Tests assert that no prompt, document content, embedding vector, or credential key ever appears in an audit row.

---

## 7. Configuration

- `config/ai.php`: `ai.knowledge.{enabled,chunk_size,chunk_overlap,top_k,max_context_tokens,min_similarity}` and `ai.embeddings.{provider,model,dimensions,timeout}`.
- `.env.example`: matching `AI_KNOWLEDGE_*`, `AI_RAG_*`, `AI_EMBEDDING_*`, and `OPENAI_EMBEDDING_API_KEY` entries. Fake embeddings are the safe offline default; no new rate limiter was introduced.

---

## 8. Permissions

- `ai.knowledge.search` and `ai.knowledge.manage` added to `RolePermissionSeeder`.
- `search`: organization admin, branch manager, loan officer, credit officer, collection officer, secretary, VICOBA Member.
- `manage`: organization admin and branch manager only — deliberately **not** VICOBA Member.

---

## 9. Security review

Greps over `app/AI` found no `eval(`, `exec(`, `shell_exec`, `passthru(`, `system(`, `call_user_func`, or `create_function`; no `DB::raw` / `whereRaw` / `selectRaw` / `orderByRaw`; no `withoutGlobalScope`/`withGlobalScope` bypass. The only `preg_replace` uses a fixed, hard-coded pattern (`/\s+/u`) for whitespace normalization with no user-controlled pattern. Retrieval failure paths audit and return empty rather than leaking.

---

## 10. Tests

New: `tests/Unit/AI/AiKnowledgeEmbeddingTest.php` (6), `tests/Feature/AI/AiKnowledgeBaseTest.php` (25).

- **Unit:** fake embedding determinism, unit norm, configured dimensions, batch/individual consistency, related-vs-unrelated ranking, chunking order and determinism, checksum stability across line endings.
- **Feature:** global retrieval for permitted staff; organization isolation; branch document requires branch assignment; group document requires group context; draft/archived/failed never retrieved; VICOBA Member limited to own org; top-K clamp rejects `top_k=100`; max-context-token budget bounds results; similarity floor drops unrelated hits; related query ranks the loan document above an unrelated one; document content never persisted as a system message; forbidden argument keys (`query`, `scope`, org/branch/group, `sql`) rejected; tool requires `ai.knowledge.search`; endpoint returns authorized results; VICOBA Member blocked from administration; admin create scoped to own org; foreign-org create and archive rejected (IDOR); own-org archive succeeds and removes the document from retrieval; ingestion idempotency; version bump and chunk replacement on content change; failed ingestion marks `failed`; wrong-dimension embeddings never scored; audit rows contain no prompt/content/vector/key; retrieval unavailable when embeddings are disabled.

---

## 11. Stop boundary

Phase 11.5 stops here. No feedback/learning, anomaly detection, predictive analytics, autonomous agents, mutating or approving tools, external web retrieval, policy auto-editing, or vector-database adoption. Phase 11.6 does not begin without a new instruction.
