# FinancePro Assistance — System-Grounded Domain Identity, Intent & Language Foundation

Audit, correction and hardening of the existing FinancePro AI. No new architecture,
no new business logic, no permission or security-model changes.

---

## 1. Root cause

**The generic answers were never hard-coded.** Searches across the repository for
`"contact your financial institution"`, `"general financial principles"`,
`"Typically, borrowers"`, `"as an AI"` and `"I don't have access to..."` return
**zero hits**. The assistant was answering from pretrained knowledge because of a
**silent fall-through to an unconstrained model call**.

The mechanism, in five steps:

| # | Location | Behaviour |
|---|---|---|
| 1 | `AiController::orchestratedContext()` | returns `[]` when `plan()` is `null` (no keyword matched) — `app/Http/Controllers/AiController.php:442` |
| 2 | `AiController::knowledgeContext()` | returns `[]` when the caller lacks `ai.knowledge.search` (`:475-477`), when retrieval returns nothing (`:481-483`), and when retrieval throws (`:486-488`) |
| 3 | `AiController::orchestratedContext()` | returns `[]` on `unauthorized` / `not_found` tool denials — `:451` |
| 4 | `AiConversationService::withSystemInstruction()` | prepended **only** `config('ai.system_instructions')` — the security prompt |
| 5 | `config/ai.php:210-220` | that prompt is a pure **security** contract: no domain identity, no source-of-truth hierarchy, no question classification, no no-fabrication rule. It says only *"Never invent financial figures; say when you do not know"* |

With zero injected data and zero grounding directive, the model answered member
balance and eligibility questions from general microfinance knowledge.

### Contributing defects (each independently verified)

- **Narrow, English-only, first-match-wins router.** `AiChatOrchestrationService.php:56-96`.
- **`LOAN_EXCLUSIONS` contains `'eligib'`** (`:46`), so *"Am I eligible for a loan?"*
  matched no capability at all.
- **Six registered-but-never-planned capabilities:** `ai.loan.eligibility.check`,
  `ai.member.view`, `ai.loan.view`, `ai.loan.application.view`,
  `ai.guarantor.eligibility.check`, `ai.collateral.requirement.check`.
- **No statement capability.** `ai.accounting.view` collapsed a statement to three
  scalars plus a boolean, discarding line detail (`AccountingIntelligenceService.php:56-58`, `:106`).
  *"Show me our income statement"* had nothing to route to.
- **`AiKnowledgeResultFormatter.php:31-34`** instructed *"Keep the answer conversational"* —
  an explicit instruction to produce free prose with no grounding.
- **No language concept existed anywhere** (repo-wide search: zero hits).
- **RAG scope precedence unimplemented** — `rankChunks()` sorts by cosine only
  (`AiKnowledgeRetrievalService.php:169`), so Global could outrank Organization.
- **Seeded knowledge is 7 public marketing documents only** — no constitution,
  no accounting policy, no rate cards, so signed-in RAG returned nothing on a fresh install.

---

## 2. Correction

### 2.1 Domain identity and the source-of-truth hierarchy

`AiDomainInstructionService` composes one contract, in order, at the single choke
point every provider call passes through:

1. **Identity** — `FinancePro Assistance`, the built-in assistance AI of the
   FinancePro platform, operated by the FinancePro team. "Not a general-purpose
   chatbot." Never self-describes as a general AI assistant.
2. **Hierarchy** — five numbered precedence rules: authoritative tool data →
   approved knowledge documents → product documentation → general knowledge
   (conceptual only) → higher priority silently wins.
3. **The original security prompt**, unchanged and still `AI_SYSTEM_INSTRUCTIONS`-overridable.
4. **Language policy** — reply in the caller's language; never translate or
   re-format an authoritative figure, never convert currency.

Configurable under `ai.identity`, `ai.answer_policy.hierarchy` and
`ai.answer_policy.language`; full opt-out via `AI_DOMAIN_POLICY_ENABLED`.

### 2.2 The grounding directive — the actual fix

`AiIntentClassifier` (`app/Enums/AiQuestionType`, five types) classifies every
question **before** the provider is called:

| Type | Directive behaviour |
|---|---|
| `financepro_system_data` | Must **not** answer from general knowledge, typical values, sector averages or assumptions. Must not state, estimate or hint at any figure. Names the record to check instead. |
| `financepro_policy` | Must not describe policy, rates, fees or process from general knowledge. Points to the official document. |
| `financepro_howto` | Must not invent navigation steps or screen names. |
| `general_educational` | May explain the concept; must **never** present a value as this member's or organization's actual position. |
| `outside_scope` | Declined, not answered. |

`AiController::ungrounded()` injects the matching directive wherever retrieval
came back empty. The public assistant gets the same treatment.

Classification is **advisory only** — it grants no permission and no scope. A
misclassification can at worst refuse an in-scope question; it can never expose data.
The default is the conservative type, not the permissive one.

### 2.3 Language foundation

`AiLanguage` (`en`, `sw`) + `AiLanguageService`. Detection is a deterministic
marker scan — no provider call, reproducible for every tenant. Two markers are
required before switching, so an English question containing one overlapping word
is not misclassified. **Voice-ready by construction:** a transcription arrives as
plain text through exactly this path, so no channel-specific logic is needed.

### 2.4 New capabilities (26 business capabilities, up from 22)

| Capability | Handler | Gate | Source of every figure |
|---|---|---|---|
| `ai.identity.view` | `IdentityTool` | `ai.use` | `config/ai.php` + `AiToolRegistry` |
| `ai.accounting.income_statement` | `IncomeStatementTool` | `ai.accounting.view` | existing `IncomeStatementService` |
| `ai.accounting.balance_sheet` | `BalanceSheetTool` | `ai.accounting.view` | existing `BalanceSheetService` |
| `ai.accounting.trial_balance` | `TrialBalanceTool` | `ai.accounting.view` | existing `TrialBalanceService` |

`AccountingStatementService` computes nothing and recalculates nothing. It inherits
`AccountingIntelligenceService` discipline: unconfigured organizations are skipped
rather than reported as zero; scope comes only from trusted `AiContextData`; statements
are per-organization; line items are capped at 40 with the omission count reported,
so a truncated statement is never presented as complete. Dates are strictly `Y-m-d`
validated — a malformed value is discarded, never guessed.

`IdentityTool` discloses **only** capabilities the caller already holds permissions
for, so it can never enumerate something the same caller would be denied (asserted in
`AiSystemGroundingTest::test_capability_enumeration_never_exposes_an_unpermitted_capability`).

### 2.5 Routing gaps closed

- Identity questions route to `ai.identity.view` and are answered **before** member
  and organization scope are considered.
- Income statement / balance sheet / trial balance route to their own capabilities,
  ahead of the accounting summary.
- `ai.member.view` (previously registered, never planned) now plans for profile questions.
- Swahili coverage across the member branches: repayments, savings, shares, welfare,
  financial summary, profile, and the `mkopo`/`mikopo` loan stem.

---

## 3. Demonstration — question → intent → tool → grounding

| Question | Classified | Capability | Grounding |
|---|---|---|---|
| What is my current loan balance? | system data | `ai.member.loans` | authoritative datum |
| How much do I have in savings? | system data | `ai.member.savings_summary` | authoritative datum |
| Am I eligible for a loan? | system data | — | **no-fabrication directive** (no safe plan/amount is derivable, so no eligibility run is invented) |
| Show me our income statement | system data | `ai.accounting.income_statement` | full statement lines |
| What is our balance sheet? | system data | `ai.accounting.balance_sheet` | full statement lines |
| Give me the trial balance | system data | `ai.accounting.trial_balance` | debit/credit integrity |
| What is our PAR30? | system data | `ai.delinquency.view` / `ai.portfolio.view` | authoritative datum |
| How many active members do we have? | system data | — | **no-fabrication directive** (no such capability exists) |
| Who are you? | how-to | `ai.identity.view` | identity from registry |
| What can you do? | how-to | `ai.identity.view` | permitted capabilities only |
| Explain the loan eligibility criteria | policy | RAG | knowledge document, or say unavailable |
| How do I reset my password in the system? | how-to | RAG | help content, or say unavailable |
| What is collateral in general? | educational | — | explained, never as actual position |
| Salio langu ni kiasi gani? | system data | member branch | **grounded + Kiswahili** |
| Je, ninaweza kupata mkopo? | system data | — | **no-fabrication directive** |
| Tuonyeshe suruhi ya mapato yetu | system data | `ai.accounting.income_statement` | full statement lines |
| Who won the football match? | outside scope | — | **declined** |
| What is the weather in Dar es Salaam? | outside scope | — | **declined** |

Before this change, every row above resolved to a bare model call. Rows now marked
*no-fabrication directive* or *declined* previously produced confident, invented,
generic prose.

---

### Request feedback (spinner)

`resources/views/ai/partials/chat.blade.php` renders the Z4drus orbit spinner
(`<!-- From Uiverse.io by Z4drus -->`, six `.slice` elements) inside a single
`#aiLoading` node, hidden with `d-none` until a question is submitted. All chat
surfaces share this partial (authenticated page, member page, floating widget,
public/guest chat), so the behaviour is identical everywhere. The CSS is scoped
under `.ai-chat-loading` so the generic Bootstrap `.container` rule cannot affect
it, and the node is toggled with `classList` only — no `innerHTML` was introduced.

---

## 4. Verification

### New tests
- `tests/Unit/AiIntentGroundingTest.php` — 12 tests, 60 assertions. Classification
  (en + sw), language detection, contract composition, per-type directives, opt-out.
- `tests/Feature/AI/AiSystemGroundingTest.php` — 10 tests, 65 assertions. Asserts the
  **real captured provider payload**: identity present, hierarchy present, directive
  present, Swahili directive, outside-scope refusal, statement envelopes, no disclosure,
  no persisted system message.

### Updated tests
- `AiEndpointSecurityTest::test_system_instructions_are_prepended_but_never_persisted` —
  extended to prove clearing `system_instructions` alone **cannot** strip the identity and
  hierarchy contract, and that full opt-out yields `[]`.
- `AiChatUiTest::test_chat_page_renders_a_single_hidden_request_spinner` — asserts one
  hidden spinner per surface, the six slices and attribution, the scoped CSS, both
  show/hide toggles, and that no `innerHTML` was introduced.
- `AiToolRegistryTest` / `AiPredictiveIntelligenceTest` — capability count 22 → 26.

### Regression (all green)
| Suite | Result |
|---|---|
| `tests/Unit` (full) | OK 88 tests, 578 assertions |
| `AiSystemGroundingTest` + `AiIntentGroundingTest` | OK 22 tests, 125 assertions |
| `AiToolRegistryTest` + `AiChatOrchestrationTest` | OK 23 tests, 388 assertions |
| `AiEndpointSecurityTest` + `AiPublicRagTest` | OK 13 tests, 83 assertions |
| `AiPredictiveIntelligenceTest` + `AiAuthorizationTest` | OK 33 tests, 166 assertions |
| `AiKnowledgeBaseTest` | OK 25 tests, 91 assertions |
| `AiChatUiTest` | OK 18 tests, 71 assertions |
| `AiIntelligenceReportingTest` | OK 53 tests, 631 assertions |
| `AiProactiveIntelligenceTest` + `AiManagementIntelligenceCenterTest` | OK 41 tests, 287 assertions |
| `AiToolEndpointTest` + `AiToolFinancialTest` + `AiFinancialIntelligenceTest` + `AiGuardrailTest` + `AiAuthorizationTest` | OK 59 tests, 379 assertions |
| `AiScheduledReportingTest` + `ManagementActionTest` | OK 66 tests, 218 assertions |
| `ManagementActionEffectivenessTest` | OK 41 tests, 153 assertions |
| `AiConversationDeleteTest` + `AiPublicConversationTest` | OK 19 tests, 53 assertions |
| `AiFeedbackTest` + `AiLearningDatasetExportTest` | OK 34 tests, 113 assertions |
| `AiEvaluationReviewTest` | OK 17 tests, 56 assertions |
| `AiGuestChatTest` + `AiPublicChatTest` + `AiConversationTest` | OK 32 tests, 104 assertions |
| `AiConfigurationTest` + `AiContextTest` + `AiDtoTest` + `AiMessageTest` + `AiModelVersionTest` + `AiProviderTest` | OK 32 tests, 88 assertions |

`php -l` clean on every touched file. `vendor/bin/pint --test` passes on all 22 touched
files.

---

## 5. Findings deliberately NOT acted on

Reported rather than silently changed, because each would alter accounting or
authorization semantics and needs an explicit decision:

1. **RAG scope precedence is still unimplemented.** `AiKnowledgeRetrievalService::rankChunks()`
   (`app/AI/Services/AiKnowledgeRetrievalService.php:169`) sorts by cosine alone, so a
   Global chunk can outrank an Organization chunk. Fixing this changes retrieval ranking
   for existing tenants and needs its own tests.
2. **The knowledge base still contains only public marketing documents.** Signed-in RAG
   returns nothing on a fresh install because no constitution, accounting policy or rate
   card is seeded. This is a content/seeding gap, not a code defect.
3. **Eligibility cannot be executed safely.** `ai.loan.eligibility.check` requires a
   `loan_plan_id` and `requested_amount` that cannot be derived from the caller's own
   trusted context without guessing. The correct outcome — *no-fabrication directive*
   rather than a fabricated check — is now enforced and tested. Deriving these safely
   would need a new server-side product-selection design.
4. **No member-count capability exists.** *"How many active members do we have?"* is
   classified as system data and correctly receives a no-fabrication directive, but
   adding a member-statistics capability is new business logic and out of scope here.

## 6. Pre-existing unrelated change

`resources/views/organizations/create.blade.php` is modified in the working tree
(div-wrapper removal). It was **not** part of this work and was deliberately left
untouched and unstaged.
