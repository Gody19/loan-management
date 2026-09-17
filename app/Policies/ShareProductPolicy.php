<?php

namespace App\Policies;

use App\Models\ShareProduct;
use App\Models\User;
use App\Services\OrganizationContext;

class ShareProductPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('share_product.view');
    }

    public function view(User $auth, ShareProduct $shareProduct): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $shareProduct->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($shareProduct->organization_id, $auth);
    }

    public function create(User $auth): bool
    {
        return $auth->can('share_product.create');
    }

    public function update(User $auth, ShareProduct $shareProduct): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $shareProduct->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($shareProduct->organization_id, $auth);
    }

    public function delete(User $auth, ShareProduct $shareProduct): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $shareProduct->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($shareProduct->organization_id, $auth);
    }
}
