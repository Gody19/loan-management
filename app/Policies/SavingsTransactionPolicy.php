<?php

namespace App\Policies;

use App\Models\SavingsTransaction;
use App\Models\User;

class SavingsTransactionPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('savings_transaction.view');
    }

    public function view(User $auth, SavingsTransaction $savingsTransaction): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $savingsTransaction->organization_id && $auth->organizations()->where('organizations.id', $savingsTransaction->organization_id)->exists();
        }

        return $auth->branches()->where('branches.id', $savingsTransaction->branch_id)->exists();
    }

    public function deposit(User $auth): bool
    {
        return $auth->can('savings_transaction.deposit');
    }

    public function withdraw(User $auth): bool
    {
        return $auth->can('savings_transaction.withdraw');
    }

    public function reverse(User $auth, SavingsTransaction $savingsTransaction): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $savingsTransaction->organization_id && $auth->organizations()->where('organizations.id', $savingsTransaction->organization_id)->exists();
        }

        return $auth->branches()->where('branches.id', $savingsTransaction->branch_id)->exists();
    }
}
