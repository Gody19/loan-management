<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('branch.view');
    }

    public function view(User $auth, Branch $branch): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->branches()->where('branches.id', $branch->id)->exists()
            || $auth->organizations()->where('organizations.id', $branch->organization_id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('branch.create');
    }

    public function update(User $auth, Branch $branch): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->branches()->where('branches.id', $branch->id)->exists()
            || $auth->organizations()->where('organizations.id', $branch->organization_id)->exists();
    }

    public function delete(User $auth, Branch $branch): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->branches()->where('branches.id', $branch->id)->exists()
            || $auth->organizations()->where('organizations.id', $branch->organization_id)->exists();
    }

    public function assignUser(User $auth, Branch $branch): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->branches()->where('branches.id', $branch->id)->exists()
            || $auth->organizations()->where('organizations.id', $branch->organization_id)->exists();
    }
}
