<?php

namespace App\Policies;

use App\Models\VicobaGroup;
use App\Models\User;
use App\Services\OrganizationContext;

class VicobaGroupPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('group.view');
    }

    public function view(User $auth, VicobaGroup $group): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->branches()->where('branches.id', $group->branch_id)->exists()) {
            return true;
        }

        $branch = $group->branch;

        return $branch->organization_id && OrganizationContext::userBelongsToOrganization($branch->organization_id, $auth);
    }

    public function create(User $auth): bool
    {
        return $auth->can('group.create');
    }

    public function update(User $auth, VicobaGroup $group): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->branches()->where('branches.id', $group->branch_id)->exists()) {
            return true;
        }

        $branch = $group->branch;

        return $branch->organization_id && OrganizationContext::userBelongsToOrganization($branch->organization_id, $auth);
    }

    public function delete(User $auth, VicobaGroup $group): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->branches()->where('branches.id', $group->branch_id)->exists()) {
            return true;
        }

        $branch = $group->branch;

        return $branch->organization_id && OrganizationContext::userBelongsToOrganization($branch->organization_id, $auth);
    }
}
