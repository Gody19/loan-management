<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WelfareFund;

class WelfareFundPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('welfare_fund.view');
    }

    public function view(User $auth, WelfareFund $welfareFund): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->organizations()->where('organizations.id', $welfareFund->organization_id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('welfare_fund.create');
    }

    public function update(User $auth, WelfareFund $welfareFund): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->organizations()->where('organizations.id', $welfareFund->organization_id)->exists();
    }

    public function delete(User $auth, WelfareFund $welfareFund): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->organizations()->where('organizations.id', $welfareFund->organization_id)->exists();
    }
}
