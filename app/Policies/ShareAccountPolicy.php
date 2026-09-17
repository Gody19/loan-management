<?php

namespace App\Policies;

use App\Models\ShareAccount;
use App\Models\User;
use App\Services\OrganizationContext;

class ShareAccountPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('share_account.view');
    }

    public function view(User $auth, ShareAccount $shareAccount): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $shareAccount->organization_id && OrganizationContext::userBelongsToOrganization($shareAccount->organization_id, $auth);
        }

        return $auth->branches()->where('branches.id', $shareAccount->branch_id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('share_account.create');
    }

    public function update(User $auth, ShareAccount $shareAccount): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $shareAccount->organization_id && OrganizationContext::userBelongsToOrganization($shareAccount->organization_id, $auth);
        }

        return $auth->branches()->where('branches.id', $shareAccount->branch_id)->exists();
    }

    public function close(User $auth, ShareAccount $shareAccount): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $shareAccount->organization_id && OrganizationContext::userBelongsToOrganization($shareAccount->organization_id, $auth);
        }

        return $auth->branches()->where('branches.id', $shareAccount->branch_id)->exists();
    }
}
