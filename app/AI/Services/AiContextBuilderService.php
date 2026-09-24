<?php

namespace App\AI\Services;

use App\AI\DTOs\AiContextData;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationContext;

/**
 * Builds the trusted AI execution context from the authenticated FinancePro
 * user. All values come from authoritative application state (Spatie role and
 * permission assignments, organization/branch membership, the user's own
 * Member record). Nothing here is derived from prompts, model output, or
 * request input.
 */
class AiContextBuilderService
{
    public function __construct()
    {
        // Resolved through the service container; keeps the builder dependency-light.
    }

    public function build(User $user): AiContextData
    {
        $roles = $this->roles($user);
        $isSuperAdmin = in_array('Super Administrator', $roles, true);

        return new AiContextData(
            userId: (int) $user->id,
            isSuperAdmin: $isSuperAdmin,
            roles: $roles,
            permissions: $this->permissions($user),
            organizationIds: $this->organizationIds($user, $isSuperAdmin),
            branchIds: $this->branchIds($user, $isSuperAdmin),
            vicobaGroupIds: $this->vicobaGroupIds($isSuperAdmin),
            memberId: $this->memberId($user),
        );
    }

    /**
     * Roles come from Spatie. No role is inferred from text.
     */
    protected function roles(User $user): array
    {
        return array_values($user->getRoleNames()->all());
    }

    /**
     * Effective permissions = role permissions unioned with any direct user
     * permissions (Spatie getAllPermissions). The AI can never grant these.
     */
    protected function permissions(User $user): array
    {
        return array_values($user->getAllPermissions()->pluck('name')->all());
    }

    /**
     * Organization scope. Super Administrators receive an explicitly *broader*
     * platform context (every organization); everyone else only their
     * authoritative membership — never merely $user->organization_id guesses.
     */
    protected function organizationIds(User $user, bool $isSuperAdmin): array
    {
        if ($isSuperAdmin) {
            return array_values(Organization::query()->pluck('id')->all());
        }

        return array_values(OrganizationContext::getUserOrganizationIds($user));
    }

    /**
     * Branch scope reflects the authoritative branch_user assignments.
     */
    protected function branchIds(User $user, bool $isSuperAdmin): array
    {
        if ($isSuperAdmin) {
            return array_values(Branch::query()->pluck('id')->all());
        }

        return array_values($user->branches()->pluck('branches.id')->all());
    }

    /**
     * There is no authoritative user↔vicoba-group assignment table in the
     * application; groups belong to organizations. A user's group reachability
     * is therefore not known directly, so no vicoba_group ids are claimed for
     * non-super users (conservative empty scope). Super Administrators get the
     * explicit platform set.
     */
    protected function vicobaGroupIds(bool $isSuperAdmin): array
    {
        if (! $isSuperAdmin) {
            return [];
        }

        return array_values(\App\Models\VicobaGroup::query()->pluck('id')->all());
    }

    /**
     * A member's AI scope can only ever be their own Member record
     * (User → Member hasOne). An AI request can never supply member_id.
     */
    protected function memberId(User $user): ?int
    {
        return $user->member?->id !== null ? (int) $user->member->id : null;
    }
}