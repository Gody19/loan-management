<?php

namespace App\AI\Policies;

use App\AI\DTOs\AiContextData;
use App\AI\Services\AiToolRegistry;

/**
 * Default-deny AI capability policy.
 *
 * This policy answers one question: "Can this authenticated user invoke this
 * specific registered AI capability?" It does NOT authorize raw database
 * access or arbitrary model output — those are separate responsibilities
 * handled by the guardrail and the explicit registry.
 *
 * Unknown or unregistered capabilities are denied by default. No capability
 * is ever authorized by role name alone; each is gated by explicit
 * permissions resolved into the trusted context.
 */
class AiToolPolicy
{
    /**
     * Argument keys that privilege escalation would target. These are never
     * accepted as capability arguments regardless of the capability schema.
     */
    public const FORBIDDEN_ARGUMENT_KEYS = [
        'organization_id',
        'branch_id',
        'vicoba_group_id',
        'member_id',
        'user_id',
        'role',
        'roles',
        'permission',
        'permissions',
        'is_super_admin',
        'scope',
        'sql',
        'query',
        'file',
        'path',
        'command',
        'class',
        'method',
        'callable',
    ];

    public function __construct(
        private readonly AiToolRegistry $registry,
    ) {}

    /**
     * Evaluate an invocation request.
     *
     * @return array{allowed: bool, reason: string|null, denied_key: string|null}
     */
    public function evaluate(AiContextData $context, string $capability, array $arguments = []): array
    {
        $deny = fn (string $reason, ?string $deniedKey = null) => [
            'allowed' => false,
            'reason' => $reason,
            'denied_key' => $deniedKey,
        ];

        if ($capability === '' || trim($capability) !== $capability) {
            return $deny(AiAuthorizationCategory::MalformedCapability->value);
        }

        $definition = $this->registry->definition($capability);

        // Null capability = unregistered = denied. No default allow.
        if ($definition === null) {
            return $deny(AiAuthorizationCategory::UnknownCapability->value);
        }

        $scope = $this->validateArguments($definition['arguments'] ?? [], $arguments);

        if (! $scope['allowed']) {
            return $deny($scope['reason'], $scope['denied_key']);
        }

        foreach ($definition['permissions'] as $permission) {
            if (! $context->hasPermission($permission)) {
                return $deny(AiAuthorizationCategory::MissingPermission->value, $permission);
            }
        }

        return [
            'allowed' => true,
            'reason' => null,
            'denied_key' => null,
        ];
    }

    /**
     * Validate capability arguments strictly against the registered schema.
     * Unknown keys and any forbidden escalation key are rejected.
     *
     * @param  array<string, string>  $schema
     */
    protected function validateArguments(array $schema, array $arguments): array
    {
        foreach ($arguments as $key => $value) {
            if (in_array($key, self::FORBIDDEN_ARGUMENT_KEYS, true)) {
                return ['allowed' => false, 'reason' => AiAuthorizationCategory::EscalationAttempt->value, 'denied_key' => $key];
            }

            if (! array_key_exists($key, $schema)) {
                return ['allowed' => false, 'reason' => AiAuthorizationCategory::ArgumentNotAllowed->value, 'denied_key' => $key];
            }

            $rule = $schema[$key];

            if ($rule === 'integer' && ! $this->isIntegerLike($value)) {
                return ['allowed' => false, 'reason' => AiAuthorizationCategory::MalformedArgument->value, 'denied_key' => $key];
            }

            if ($rule === 'string' && ! is_string($value)) {
                return ['allowed' => false, 'reason' => AiAuthorizationCategory::MalformedArgument->value, 'denied_key' => $key];
            }
        }

        return ['allowed' => true, 'reason' => null, 'denied_key' => null];
    }

    private function isIntegerLike(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        return is_string($value) && $value !== '' && ctype_digit($value);
    }
}