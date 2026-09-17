<?php

namespace App\Policies;

use App\Models\ShareTransaction;
use App\Models\User;
use App\Services\OrganizationContext;

class ShareTransactionPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('share_transaction.view');
    }

    public function view(User $auth, ShareTransaction $shareTransaction): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $shareTransaction->organization_id && OrganizationContext::userBelongsToOrganization($shareTransaction->organization_id, $auth);
        }

        return $auth->branches()->where('branches.id', $shareTransaction->branch_id)->exists();
    }

    public function purchase(User $auth): bool
    {
        return $auth->can('share_transaction.purchase');
    }

    public function redeem(User $auth): bool
    {
        return $auth->can('share_transaction.redeem');
    }

    public function reverse(User $auth, ShareTransaction $shareTransaction): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $shareTransaction->organization_id && OrganizationContext::userBelongsToOrganization($shareTransaction->organization_id, $auth);
        }

        return $auth->branches()->where('branches.id', $shareTransaction->branch_id)->exists();
    }
}
