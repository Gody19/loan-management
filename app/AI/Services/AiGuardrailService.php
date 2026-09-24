<?php

namespace App\AI\Services;

use App\AI\DTOs\AiContextData;
use App\AI\Exceptions\AiAuthorizationException;
use App\AI\Exceptions\AiUnavailableException;
use App\AI\Policies\AiAuthorizationCategory;
use App\AI\Policies\AiToolPolicy;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AuditService;

/**
 * Security boundary every AI capability must pass through.
 *
 * Responsibilities:
 *  - verify the AI feature is enabled (a disabled feature is unavailable, 503);
 *  - verify an authenticated user produced the request;
 *  - build the trusted context (roles/permissions/scopes from FinancePro state);
 *  - enforce default-deny via the AiToolPolicy on an explicit registry;
 *  - reject scope escalation and malformed capability requests;
 *  - revalidate conversation access against the trusted context (no IDOR);
 *  - audit every allowed/denied authorization attempt.
 *
 * The guardrail never executes business tools and never calls a provider.
 */
class AiGuardrailService
{
    public function __construct(
        private readonly AiProviderService $providers,
        private readonly AiContextBuilderService $contextBuilder,
        private readonly AiToolPolicy $policy,
        private readonly AuditService $audit,
    ) {}

    /**
     * Authorize an AI capability invocation.
     *
     * @return AiContextData the trusted context for the authorized capability
     *
     * @throws AiUnavailableException    when the AI feature is disabled
     * @throws AiAuthorizationException  when the invocation is denied
     */
    public function authorize(string $capability, array $arguments = [], ?User $user = null): AiContextData
    {
        if (! $this->providers->isEnabled()) {
            throw new AiUnavailableException();
        }

        $user = $user ?? auth()->user();

        if (! $user) {
            $this->auditDecision('ai.authorization.denied', [
                'capability' => $capability,
                'reason' => AiAuthorizationCategory::Unauthenticated->value,
            ]);

            throw new AiAuthorizationException(
                category: AiAuthorizationCategory::Unauthenticated->value,
                capability: $capability,
            );
        }

        $context = $this->contextBuilder->build($user);

        $decision = $this->policy->evaluate($context, $capability, $arguments);

        $this->auditDecision(
            $decision['allowed'] ? 'ai.authorization.allowed' : 'ai.authorization.denied',
            $this->auditMetadata($context, $capability, $decision, $arguments),
        );

        if (! $decision['allowed']) {
            throw new AiAuthorizationException(
                category: AiAuthorizationCategory::from($decision['reason'])->value,
                capability: $capability,
                metadata: [
                    'denied_key' => $decision['denied_key'],
                ],
            );
        }

        return $context;
    }

    /**
     * Revalidate conversation access purely from the trusted context. The
     * model cannot change scope by mentioning identifiers — the conversation
     * must already belong to the user, their organization, or be platform
     * scope for a Super Administrator.
     *
     * VICOBA Members are held to the strictest scope: owner-only.
     */
    public function checkConversationAccess(AiConversation $conversation, AiContextData $context): void
    {
        if ($context->isSuperAdmin) {
            return;
        }

        if ($conversation->user_id !== null && (int) $conversation->user_id === $context->userId) {
            return;
        }

        if ($context->hasRole('VICOBA Member')) {
            $this->denyConversationAccess($context, AiAuthorizationCategory::ConversationNotOwned);
        }

        if ($conversation->organization_id !== null
            && $context->belongsToOrganization((int) $conversation->organization_id)) {
            return;
        }

        $this->denyConversationAccess($context, AiAuthorizationCategory::ConversationScopeViolation);
    }

    /**
     * Reusable denial used by the conversation-access check. Audits and throws.
     */
    protected function denyConversationAccess(AiContextData $context, AiAuthorizationCategory $category)
    {
        $this->auditDecision('ai.authorization.denied', [
            'user_id' => $context->userId,
            'capability' => 'conversation.access',
            'reason' => $category->value,
        ]);

        throw new AiAuthorizationException(
            category: $category->value,
            capability: 'conversation.access',
        );
    }

    /**
     * Write an allowed/denied authorization event with safe metadata only.
     * Prompts, secrets, and raw provider content are never recorded.
     */
    protected function auditDecision(string $event, array $metadata): void
    {
        $this->audit->log($event, null, [], $metadata);
    }

    protected function auditMetadata(AiContextData $context, string $capability, array $decision, array $arguments): array
    {
        return [
            'user_id' => $context->userId,
            'capability' => $capability,
            'reason' => $decision['reason'],
            'denied_key' => $decision['denied_key'],
            'roles' => array_values($context->roles),
            'permission_count' => count($context->permissions),
            'organization_count' => count($context->organizationIds),
            'branch_count' => count($context->branchIds),
            'member_id' => $context->memberId,
            'argument_keys' => array_keys($arguments),
        ];
    }
}