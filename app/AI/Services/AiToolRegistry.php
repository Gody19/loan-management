<?php

namespace App\AI\Services;

/**
 * Explicit registry of authorized AI capabilities.
 *
 * A capability is only usable if it is registered here with an explicit
 * permission gate and an explicit argument schema. There is no dynamic
 * discovery: model output can never name a class, method, SQL statement, or
 * an unregistered capability and have it resolved.
 *
 * Registered Phase 11.2 capabilities are limited to the conversation/chat
 * surface built in Phase 11.1. No business-data tool exists yet; the moment a
 * business tool is added (Phase 11.3) it MUST be registered here with a
 * permission gate and argument schema before it becomes callable.
 */
class AiToolRegistry
{
    public const SCOPE_USER_ORG = 'user_org';
    public const SCOPE_PLATFORM = 'platform';

    /**
     * @var array<string, array{permissions: string[], scope: string, arguments: array<string, string>}>
     */
    protected const CAPABILITIES = [
        'ai.conversation.list' => [
            'permissions' => ['ai.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => [],
        ],
        'ai.conversation.read' => [
            'permissions' => ['ai.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['conversation_id' => 'integer'],
        ],
        'ai.chat' => [
            'permissions' => ['ai.use'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['conversation_id' => 'integer'],
        ],
    ];

    public function registeredCapabilities(): array
    {
        return array_keys(self::CAPABILITIES);
    }

    public function has(string $capability): bool
    {
        return isset(self::CAPABILITIES[$capability]);
    }

    public function definition(string $capability): ?array
    {
        return self::CAPABILITIES[$capability] ?? null;
    }

    public function requiredPermissions(string $capability): array
    {
        return self::CAPABILITIES[$capability]['permissions'] ?? [];
    }

    public function argumentRules(string $capability): array
    {
        return self::CAPABILITIES[$capability]['arguments'] ?? [];
    }

    public function scope(string $capability): string
    {
        return self::CAPABILITIES[$capability]['scope'] ?? self::SCOPE_USER_ORG;
    }
}