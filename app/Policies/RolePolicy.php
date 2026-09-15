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
     */
    public function create(User $auth): bool
    {
        return $auth->can('role.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $auth, Role $role): bool
    {
        return $auth->can('role.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $auth, Role $role): bool
    {
        return $auth->can('role.delete');
    }
}
