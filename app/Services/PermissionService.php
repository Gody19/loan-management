<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionService
{
    /**
     * Get permissions grouped by module.
     */
    public function getGroupedPermissions(): Collection
    {
        return Permission::all()->groupBy(function (Permission $permission) {
            $parts = explode('.', $permission->name);
            return $parts[0];
        });
    }

    /**
     * Sync user permissions.
     */
    public function syncUserPermissions(User $user, array $permissions): void
    {
        $user->syncPermissions($permissions);
    }

    /**
     * Sync role permissions.
     */
    public function syncRolePermissions(Role $role, array $permissions): void
    {
        $role->syncPermissions($permissions);
    }

    /**
     * Assign role to user.
     */
    public function assignRole(User $user, string $role): void
    {
        $user->assignRole($role);
    }

    /**
     * Remove role from user.
     */
    public function removeRole(User $user, string $role): void
    {
        $user->removeRole($role);
    }

    /**
     * Get all available modules from permissions.
     */
    public function getModules(): array
    {
        return Permission::all()
            ->pluck('name')
            ->map(fn ($name) => explode('.', $name)[0])
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get all available actions from permissions.
     */
    public function getActions(): array
    {
        return Permission::all()
            ->pluck('name')
            ->map(fn ($name) => explode('.', $name)[1] ?? '')
            ->unique()
            ->filter()
            ->values()
            ->toArray();
    }
}
