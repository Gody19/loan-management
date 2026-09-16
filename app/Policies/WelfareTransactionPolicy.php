<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WelfareTransaction;

class WelfareTransactionPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('welfare_transaction.view');
    }

    public function view(User $auth, WelfareTransaction $welfareTransaction): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $welfareTransaction->organization_id && $auth->organizations()->where('organizations.id', $welfareTransaction->organization_id)->exists();
        }

        return $auth->branches()->where('branches.id', $welfareTransaction->branch_id)->exists();
    }

    public function contribute(User $auth): bool
    {
        return $auth->can('welfare_transaction.contribute');
    }

    public function benefit(User $auth): bool
    {
        return $auth->can('welfare_transaction.benefit');
    }

    public function reverse(User $auth, WelfareTransaction $welfareTransaction): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $welfareTransaction->organization_id && $auth->organizations()->where('organizations.id', $welfareTransaction->organization_id)->exists();
        }

        return $auth->branches()->where('branches.id', $welfareTransaction->branch_id)->exists();
    }
}
