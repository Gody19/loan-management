<?php

namespace App\Policies;

use App\Models\AccountingPeriod;
use App\Models\User;
use App\Services\OrganizationContext;

class AccountingPeriodPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('accounting.view');
    }

    public function view(User $auth, AccountingPeriod $period): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $period->organization_id) {
            return false;
        }

        return OrganizationContext::userBelongsToOrganization($period->organization_id, $auth);
    }

    public function create(User $auth): bool
    {
        return $auth->can('accounting.create');
    }

    public function update(User $auth, AccountingPeriod $period): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $period->organization_id) {
            return false;
        }

        return OrganizationContext::userBelongsToOrganization($period->organization_id, $auth);
    }

    public function delete(User $auth, AccountingPeriod $period): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $period->organization_id) {
            return false;
        }

        return OrganizationContext::userBelongsToOrganization($period->organization_id, $auth);
    }
}
