<?php

namespace App\AI\DTOs;

/**
 * Immutable, trusted AI execution context derived exclusively from the
 * authenticated FinancePro user and the authoritative Spatie role/permission
 * and organization/branch assignment state.
 *
 * The model never contributes to or mutates this context. There is no path
 * from prompt text, request JSON, or query parameters into these values.
 */
final class AiContextData
{
    /**
     * @param  int[]  $organizationIds  Authoritative organization assignments.
     * @param  int[]  $branchIds        Authoritative branch assignments.
     * @param  int[]  $vicobaGroupIds   Authoritative vicoba-group assignments (empty when none exist for the user).
     */
    public function __construct(
        public readonly int $userId,
        public readonly bool $isSuperAdmin,
        public readonly array $roles,
        public readonly array $permissions,
        public readonly array $organizationIds,
        public readonly array $branchIds,
        public readonly array $vicobaGroupIds,
        public readonly ?int $memberId,
    ) {}

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    /**
     * Whether the user may act within the given organization scope.
     */
    public function belongsToOrganization(int $organizationId): bool
    {
        return in_array($organizationId, $this->organizationIds, true);
    }

    /**
     * Whether the user may act within the given branch scope.
     */
    public function belongsToBranch(int $branchId): bool
    {
        return in_array($branchId, $this->branchIds, true);
    }
}