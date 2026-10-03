<?php

namespace App\AI\Services;

use App\AI\DTOs\AiMessageData;
use App\AI\DTOs\AiRequestData;
use App\AI\DTOs\AiResponseData;
use App\AI\Exceptions\AiUnavailableException;
use App\Enums\AiConversationStatus;
use App\Enums\AiConversationType;
use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OrganizationContext;
use Illuminate\Support\Collection;

/**
 * Conversation lifecycle: create, retrieve, append, bound history, usage
 * recording, close/archive. Ownership and tenant boundaries are enforced on
 * every retrieval. The service intentionally performs no business-domain
 * queries (no Member, Loan, SavingsAccount, JournalEntry, ...).
 */
class AiConversationService
{
    public function __construct(
        private readonly AiProviderService $providerService,
        private readonly AuditService $audit,
        private readonly AiDomainInstructionService $domain,
    ) {}

    /**
     * Create a new conversation. Tenant context is supplied by the caller and
     * never fabricated here; null means platform-level (not fabricated).
     */
    public function create(
        ?User $user = null,
        ?int $organizationId = null,
        ?int $branchId = null,
        ?int $vicobaGroupId = null,
        ?string $title = null,
    ): AiConversation {
        $conversation = AiConversation::create([
            'user_id' => $user?->id ?? auth()->id(),
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'vicoba_group_id' => $vicobaGroupId,
            'title' => $title,
            'status' => AiConversationStatus::Active,
        ]);

        $this->audit->log('ai.conversation.created', $conversation, [], [
            'conversation_id' => $conversation->id,
        ]);

        return $conversation;
    }

    /**
     * Create a public (anonymous, landing-page) conversation anchored to a
     * server-generated uuid stored in the visitor session. The uuid is the
     * only handle to the conversation and is never supplied by the browser.
     */
    public function createPublic(string $uuid): AiConversation
    {
        $conversation = AiConversation::create([
            'user_id' => null,
            'organization_id' => null,
            'branch_id' => null,
            'vicoba_group_id' => null,
            'title' => 'Public visitor chat',
            'status' => AiConversationStatus::Active,
            'type' => AiConversationType::Public,
            'uuid' => $uuid,
        ]);

        $this->audit->log('ai.conversation.created', $conversation, [], [
            'conversation_id' => $conversation->id,
            'type' => AiConversationType::Public->value,
        ]);

        return $conversation;
    }

    /**
     * Locate a public conversation by its session-anchored uuid. Only public
     * conversations are ever matched, so this can never resolve a private
     * (owner/org) conversation.
     */
    public function findPublicByUuid(string $uuid): ?AiConversation
    {
        return AiConversation::query()
            ->public()
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Retrieve a conversation for a user with ownership/tenant enforcement.
     */
    public function findForUser(int $id, ?User $user = null): AiConversation
    {
        $conversation = AiConversation::findOrFail($id);
        $this->authorizeAccess($conversation, $user);

        return $conversation;
    }

    /**
     * Conversations the user is allowed to list: their own, or conversations
     * belonging to an organization they belong to. Super Administrators see all.
     * VICOBA Members are held to the strictest scope and only see their own.
     */
    public function listForUser(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return collect();
        }

        $query = AiConversation::query()
            ->private()
            ->orderByDesc('updated_at');

        if ($user->hasRole('VICOBA Member')) {
            $query->where('user_id', $user->id);
        } elseif (! $user->hasRole('Super Administrator')) {
            $orgIds = OrganizationContext::getUserOrganizationIds($user);

            $query->where(function ($q) use ($user, $orgIds) {
                $q->where('user_id', $user->id)
                    ->orWhereIn('organization_id', $orgIds);
            });
        }

        return $query->get();
    }

    /**
     * Persist a message on a conversation.
     */
    public function appendMessage(
        AiConversation $conversation,
        AiMessageRole $role,
        string $content,
        array $usage = [],
    ): AiMessage {
        $message = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'provider_message_id' => $usage['provider_message_id'] ?? null,
            'input_tokens' => $usage['input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
            'model' => $usage['model'] ?? null,
            'metadata' => $usage['metadata'] ?? [],
        ]);

        $conversation->touch();

        $this->audit->log('ai.message.created', $message, [], [
            'conversation_id' => $conversation->id,
            'role' => $role->value,
            'message_id' => $message->id,
        ]);

        return $message;
    }

    /**
     * Ordered messages of a conversation (oldest first).
     */
    public function messages(AiConversation $conversation): Collection
    {
        return $conversation->messages()->orderBy('id')->get();
    }

    /**
     * The most recent assistant message on a conversation. Callers that need
     * to address the response they just received use this instead of trusting
     * an id supplied by the client.
     */
    public function lastAssistantMessage(AiConversation $conversation): ?AiMessage
    {
        return $conversation->messages()
            ->where('role', AiMessageRole::Assistant->value)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Bounded conversation history as normalized message data. Never sends an
     * unbounded number of messages to a provider.
     */
    public function history(AiConversation $conversation, ?int $limit = null): array
    {
        $limit = $limit ?? (int) config('ai.max_history_messages', 12);

        return $conversation->messages()
            ->orderByDesc('id')
            ->limit(abs($limit))
            ->get()
            ->reverse()
            ->map(fn (AiMessage $message) => new AiMessageData(
                $message->role,
                $message->content,
            ))
            ->values()
            ->all();
    }

    /**
     * Delete the oldest messages beyond the kept window. Returns rows removed.
     */
    public function pruneHistory(AiConversation $conversation, int $keep): int
    {
        $oldestIds = $conversation->messages()
            ->orderByDesc('id')
            ->pluck('id')
            ->slice($keep)
            ->all();

        if (empty($oldestIds)) {
            return 0;
        }

        $deleted = AiMessage::whereIn('id', $oldestIds)->delete();
        $conversation->touch();

        return (int) $deleted;
    }

    /**
     * Closed-loop action: append the user message, ask the provider, persist the
     * assistant reply together with usage, and update conversation metadata.
     *
     * A configured system instruction is prepended to the provider request.
     * It is guidance only and is never persisted as a conversation message.
     *
     * Optional $systemContext carries additional System messages into the
     * provider request (e.g. an authoritative tool result for the model to
     * summarize). Like the system instruction, these are never persisted as
     * conversation messages.
     *
     * @param  AiMessageData[]  $systemContext
     *
     * @throws AiUnavailableException
     */
    public function send(AiConversation $conversation, string $content, array $systemContext = []): AiResponseData
    {
        $this->appendMessage($conversation, AiMessageRole::User, $content);

        $messages = $this->withSystemInstruction($this->history($conversation), $systemContext);

        $request = new AiRequestData(
            messages: $messages,
            model: $this->providerService->defaultModel(),
            temperature: config('ai.temperature') !== null ? (float) config('ai.temperature') : null,
            maxOutputTokens: config('ai.max_output_tokens') !== null ? (int) config('ai.max_output_tokens') : null,
            metadata: [
                'conversation_id' => $conversation->id,
                'user_id' => $conversation->user_id,
            ],
            conversationId: (int) $conversation->id,
        );

        $response = $this->providerService->generate($request);

        $this->appendMessage($conversation, AiMessageRole::Assistant, $response->content, [
            'provider_message_id' => $response->providerRequestId,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'total_tokens' => $response->totalTokens,
            'model' => $response->model,
            'metadata' => $response->metadata,
        ]);

        $conversation->update([
            'provider' => $response->provider,
            'model' => $response->model,
            'status' => AiConversationStatus::Active,
        ]);

        $this->audit->log('ai.provider.requested', $conversation, [], [
            'provider' => $response->provider,
            'model' => $response->model,
        ]);

        return $response;
    }

    /**
     * Close/archive a conversation.
     */
    public function close(AiConversation $conversation): AiConversation
    {
        $conversation->update(['status' => AiConversationStatus::Archived]);

        $this->audit->log('ai.conversation.closed', $conversation, [], [
            'conversation_id' => $conversation->id,
        ]);

        return $conversation;
    }

    /**
     * Permanently delete a conversation (messages, feedback and evaluations
     * cascade). This is deliberately stricter than read access: only the owner
     * (or a Super Administrator) may delete, so an organization-shared chat
     * can never be removed by a colleague who merely holds read access.
     */
    public function destroy(AiConversation $conversation, ?User $user = null): void
    {
        $user = $user ?? auth()->user();

        $isOwner = $conversation->user_id !== null
            && $user !== null
            && (int) $conversation->user_id === (int) $user->id;

        if (! $user || ! ($isOwner || $user->hasRole('Super Administrator'))) {
            abort(403, 'You may only delete conversations you own.');
        }

        $conversation->delete();

        $this->audit->log('ai.conversation.deleted', $conversation, [], [
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Stateless completion for public, unauthenticated visitors (landing-page
     * FAQ chat). Deliberately no conversation: nothing is persisted, no member
     * data or knowledge base is reachable, and the reply is never personalized.
     * The configured system instruction is kept, with a guest-scope note added
     * so the model never claims access to account or organization data.
     *
     * @throws AiUnavailableException
     */
    public function guestCompletion(string $content): AiResponseData
    {
        $note = trim((string) config('ai.guest_system_note', ''));

        $systemContext = $note !== ''
            ? [new AiMessageData(AiMessageRole::System, $note)]
            : [];

        $messages = $this->withSystemInstruction(
            [new AiMessageData(AiMessageRole::User, $content)],
            $systemContext,
        );

        $request = new AiRequestData(
            messages: $messages,
            model: $this->providerService->defaultModel(),
            temperature: config('ai.temperature') !== null ? (float) config('ai.temperature') : null,
            maxOutputTokens: config('ai.max_output_tokens') !== null ? (int) config('ai.max_output_tokens') : null,
            metadata: [
                'scope' => 'public-guest',
                'persisted' => false,
            ],
        );

        return $this->providerService->generate($request);
    }

    public function isEnabled(): bool
    {
        return $this->providerService->isEnabled();
    }

    public function isAvailable(?string $providerName = null): bool
    {
        return $this->providerService->isAvailable($providerName);
    }

    /**
     * Prepend the assistant's domain contract, followed by any caller-supplied
     * system context (e.g. tool results or a grounding directive), then the
     * history.
     *
     * The composed contract is the single place the assistant learns its
     * identity, the source-of-truth hierarchy and its language, so every
     * channel — web chat, the public landing-page assistant, and any future
     * voice channel — is grounded identically because they all reach the
     * provider through here.
     *
     * @param  AiMessageData[]  $messages
     * @param  AiMessageData[]  $systemContext
     * @return AiMessageData[]
     */
    protected function withSystemInstruction(array $messages, array $systemContext = []): array
    {
        $instructions = $this->domain->base($this->instructingMessage($messages))
            ?: trim((string) config('ai.system_instructions', ''));

        $context = array_values(array_filter(
            $systemContext,
            fn ($message) => $message instanceof AiMessageData,
        ));

        if ($instructions !== '') {
            $context = array_merge(
                [new AiMessageData(AiMessageRole::System, $instructions)],
                $context,
            );
        }

        if ($context === []) {
            return $messages;
        }

        return array_merge($context, $messages);
    }

    /**
     * The user's own latest message, used only to resolve the response
     * language. Reads the history the caller already built — never the
     * conversation record, and never a system or tool-result message, so a
     * retrieved document can never influence language detection.
     *
     * @param  AiMessageData[]  $messages
     */
    protected function instructingMessage(array $messages): ?string
    {
        for ($index = count($messages) - 1; $index >= 0; $index--) {
            if ($messages[$index]->role === AiMessageRole::User) {
                return $messages[$index]->content;
            }
        }

        return null;
    }

    /**
     * Ownership and tenant enforcement. A conversation belonging to Organization
     * A is never visible to a user of Organization B.
     */
    protected function authorizeAccess(AiConversation $conversation, ?User $user = null): void
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            abort(403, 'Unauthorized access to this conversation.');
        }

        if ($user->hasRole('Super Administrator')) {
            return;
        }

        if ($conversation->user_id !== null && (int) $conversation->user_id === (int) $user->id) {
            return;
        }

        // VICOBA Members: strictest scope — owner-only, never org-shared.
        if ($user->hasRole('VICOBA Member')) {
            abort(403, 'Unauthorized access to this conversation.');
        }

        if ($conversation->organization_id !== null
            && OrganizationContext::modelBelongsToUserOrganization($conversation, $user)) {
            return;
        }

        abort(403, 'Unauthorized access to this conversation.');
    }
}
