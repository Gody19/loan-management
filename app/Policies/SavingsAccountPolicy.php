<?php

namespace App\Policies;

use App\Models\SavingsAccount;
use App\Models\User;

class SavingsAccountPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('savings_account.view');
    }

    public function view(User $auth, SavingsAccount $savingsAccount): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $savingsAccount->organization_id && $auth->organizations()->where('organizations.id', $savingsAccount->organization_id)->exists();
        }

        return $auth->branches()->where('branches.id', $savingsAccount->branch_id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('savings_account.create');
    }

    public function close(User $auth, SavingsAccount $savingsAccount): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $savingsAccount->organization_id && $auth->organizations()->where('organizations.id', $savingsAccount->organization_id)->exists();
        }

        return $auth->branches()->where('branches.id', $savingsAccount->branch_id)->exists();
    }

    public function deposit(User $auth, SavingsAccount $savingsAccount): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $savingsAccount->organization_id && $auth->organizations()->where('organizations.id', $savingsAccount->organization_id)->exists();
        }

        return $auth->branches()->where('branches.id', $savingsAccount->branch_id)->exists();
    }

    public function withdraw(User $auth, SavingsAccount $savingsAccount): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $savingsAccount->organization_id && $auth->organizations()->where('organizations.id', $savingsAccount->organization_id)->exists();
        }

        return $auth->branches()->where('branches.id', $savingsAccount->branch_id)->exists();
    }
}
