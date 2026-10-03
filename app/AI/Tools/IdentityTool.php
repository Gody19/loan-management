<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\Services\AiToolRegistry;
use App\Models\User;

/**
 * ai.identity.view — who the assistant is and what it is currently allowed to
 * do for this caller.
 *
 * Identity and capability questions were previously unanswerable from any
 * source, so they reached the model with no grounding and it improvised a
 * "general AI assistant" persona and a generic feature list. Both halves of
 * that answer are authoritative FinancePro facts available on the server:
 *
 *  - the identity is the configured platform identity (config/ai.php);
 *  - the capability list is read straight from AiToolRegistry and filtered to
 *    capabilities this caller actually holds the permissions for.
 *
 * Filtering matters: the registry is the single source of truth, but a
 * capability the caller may not use is not disclosed, so this capability can
 * never enumerate a capability that the same caller would be denied.
 *
 * It reads no member, loan or financial record.
 */
class IdentityTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolRegistry $registry,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $capabilities = [];

        foreach ($this->registry->businessCapabilities() as $definition) {
            $permitted = true;

            foreach ($definition['permissions'] as $permission) {
                if (! $context->hasPermission($permission)) {
                    $permitted = false;
                    break;
                }
            }

            if ($permitted) {
                $capabilities[] = $definition['description'];
            }
        }

        return [
            'assistant' => [
                'name' => (string) config('ai.identity.name', 'FinancePro Assistance'),
                'platform' => (string) config('ai.identity.platform', 'FinancePro'),
                'role' => (string) config('ai.identity.role', ''),
                'operator' => (string) config('ai.identity.operator', ''),
            ],
            'capability_count' => count($capabilities),
            'capabilities' => $capabilities,
            'authenticated_scope' => [
                'organization_count' => count($context->organizationIds),
                'branch_count' => count($context->branchIds),
                'has_linked_member_record' => $context->memberId !== null,
            ],
        ];
    }
}
