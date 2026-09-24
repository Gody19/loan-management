<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $auth): bool
    {
        return $auth->can('role.view');
    }

    /**
     * Determine whether the user can create models.
     *
     * Role definitions are global (not tenant scoped), so only a
     * Super Administrator may mutate them.
     */
    public function create(User $auth): bool
    {
        return $auth->hasRole('Super Administrator') && $auth->can('role.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $auth, Role $role): bool
    {
        return $auth->hasRole('Super Administrator') && $auth->can('role.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $auth, Role $role): bool
    {
        return $auth->hasRole('Super Administrator') && $auth->can('role.delete');
    }
}
