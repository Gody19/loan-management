<?php

namespace App\Policies;

use App\Models\WelfareAccount;
use App\Models\User;
use App\Services\OrganizationContext;

class WelfareAccountPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('welfare_account.view');
    }

    public function view(User $auth, WelfareAccount $welfareAccount): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $welfareAccount->organization_id && OrganizationContext::userBelongsToOrganization($welfareAccount->organization_id, $auth);
        }

        return $auth->branches()->where('branches.id', $welfareAccount->branch_id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('welfare_account.create');
    }

    public function update(User $auth, WelfareAccount $welfareAccount): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $welfareAccount->organization_id && OrganizationContext::userBelongsToOrganization($welfareAccount->organization_id, $auth);
        }

        return $auth->branches()->where('branches.id', $welfareAccount->branch_id)->exists();
    }

    public function close(User $auth, WelfareAccount $welfareAccount): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $welfareAccount->organization_id && OrganizationContext::userBelongsToOrganization($welfareAccount->organization_id, $auth);
        }

        return $auth->branches()->where('branches.id', $welfareAccount->branch_id)->exists();
    }
}
