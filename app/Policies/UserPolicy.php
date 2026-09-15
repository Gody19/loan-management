<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $auth): bool
    {
        return $auth->can('user.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $auth, User $user): bool
    {
        return $auth->can('user.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $auth): bool
    {
        return $auth->can('user.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $auth, User $user): bool
    {
        return $auth->can('user.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $auth, User $user): bool
    {
        return $auth->can('user.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $auth, User $user): bool
    {
        return $auth->can('user.update');
    }
}
