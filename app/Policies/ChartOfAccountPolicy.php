<?php

namespace App\Policies;

use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\OrganizationContext;

class ChartOfAccountPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('accounting.view');
    }

    public function view(User $auth, ChartOfAccount $account): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $account->organization_id) {
            return false;
        }

        return OrganizationContext::userBelongsToOrganization($account->organization_id, $auth);
    }

    public function create(User $auth): bool
    {
        return $auth->can('accounting.create');
    }

    public function update(User $auth, ChartOfAccount $account): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $account->organization_id) {
            return false;
        }

        return OrganizationContext::userBelongsToOrganization($account->organization_id, $auth);
    }

    public function delete(User $auth, ChartOfAccount $account): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $account->organization_id) {
            return false;
        }

        return OrganizationContext::userBelongsToOrganization($account->organization_id, $auth);
    }
}
