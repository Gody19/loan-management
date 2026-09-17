<?php

namespace App\Policies;

use App\Models\WelfareFund;
use App\Models\User;
use App\Services\OrganizationContext;

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

        if (! $welfareFund->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($welfareFund->organization_id, $auth);
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

        if (! $welfareFund->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($welfareFund->organization_id, $auth);
    }

    public function delete(User $auth, WelfareFund $welfareFund): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $welfareFund->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($welfareFund->organization_id, $auth);
    }
}
