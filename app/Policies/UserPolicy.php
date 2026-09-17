<?php

namespace App\Policies;

use App\Models\User;
use App\Services\OrganizationContext;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     * Scoped to user's organizations — Super Admin sees all.
     */
    public function viewAny(User $auth): bool
    {
        return $auth->can('user.view');
    }

    /**
     * Determine whether the user can view the model.
     * Users can only view users within their organization(s).
     */
    public function view(User $auth, User $user): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        // Users can view their own profile
        if ($auth->id === $user->id) {
            return true;
        }

        return OrganizationContext::userBelongsToAnyOf($user, $auth);
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
     * Users can only update users within their organization(s).
     */
    public function update(User $auth, User $user): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        // Users can update their own profile
        if ($auth->id === $user->id) {
            return true;
        }

        return OrganizationContext::userBelongsToAnyOf($user, $auth);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $auth, User $user): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $auth, User $user): bool
    {
        return $auth->can('user.update');
    }
}
