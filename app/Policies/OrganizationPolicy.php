<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationContext;

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

        if (! $organization->id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($organization->id, $auth);
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

        if (! $organization->id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($organization->id, $auth);
    }

    public function delete(User $auth, Organization $organization): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $organization->id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($organization->id, $auth);
    }

    public function assignUser(User $auth, Organization $organization): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $organization->id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($organization->id, $auth);
    }
}
