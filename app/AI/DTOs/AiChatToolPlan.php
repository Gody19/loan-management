<?php

namespace App\AI\DTOs;

use App\AI\Policies\AiToolPolicy;
use App\AI\Services\AiToolRegistry;

/**
 * Immutable result of the server-side chat-orchestration step.
 *
 * The normal chat UI never selects capabilities or supplies arguments: the
 * server itself decides, from the user's trusted context and the plain-text
 * question, whether a registered business capability should be consulted.
 * Each plan is re-gated by the capabilities' registered permissions before
 * any tool runs, and all arguments are derived from the trusted context
 * (never from the raw message).
 */
final class AiChatToolPlan
{
    public function __construct(
        public readonly string $capability,
        /** @var array<string, mixed> */
        public readonly array $arguments,
        public readonly string $label,
    ) {}

    /**
     * Whether the acting context is permitted to run the planned capability.
     * Independent safety net on top of the orchestrator's own checks — the
     * registry's explicit permission gate is never bypassed.
     */
    public function permittedFor(AiContextData $context, AiToolRegistry $registry): bool
    {
        $authorized = $this->argumentsAreSafe();

        foreach ($registry->requiredPermissions($this->capability) as $permission) {
            if (! $context->hasPermission($permission)) {
                $authorized = false;
            }
        }

        return $authorized;
    }

    /**
     * Plans are constructed only from context-derived values, but the policy
     * is applied again here so no argument can ever carry a forbidden key.
     */
    protected function argumentsAreSafe(): bool
    {
        foreach (array_keys($this->arguments) as $key) {
            if (in_array($key, AiToolPolicy::FORBIDDEN_ARGUMENT_KEYS, true)) {
                return false;
            }
        }

        return true;
    }
}