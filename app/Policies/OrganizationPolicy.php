<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('organization.view');
    }

    public function view(User $auth, Organization $organization): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->organizations()->where('organizations.id', $organization->id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('organization.create');
    }

    public function update(User $auth, Organization $organization): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->organizations()->where('organizations.id', $organization->id)->exists();
    }

    public function delete(User $auth, Organization $organization): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->organizations()->where('organizations.id', $organization->id)->exists();
    }

    public function assignUser(User $auth, Organization $organization): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->organizations()->where('organizations.id', $organization->id)->exists();
    }
}
