<?php

namespace App\Policies;

use App\Models\SavingsProduct;
use App\Models\User;
use App\Services\OrganizationContext;

class SavingsProductPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('savings_product.view');
    }

    public function view(User $auth, SavingsProduct $savingsProduct): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $savingsProduct->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($savingsProduct->organization_id, $auth);
    }

    public function create(User $auth): bool
    {
        return $auth->can('savings_product.create');
    }

    public function update(User $auth, SavingsProduct $savingsProduct): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $savingsProduct->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($savingsProduct->organization_id, $auth);
    }

    public function delete(User $auth, SavingsProduct $savingsProduct): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $savingsProduct->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($savingsProduct->organization_id, $auth);
    }
}
