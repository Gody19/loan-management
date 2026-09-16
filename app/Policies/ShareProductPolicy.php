<?php

namespace App\Policies;

use App\Models\ShareProduct;
use App\Models\User;

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

        if (!$shareProduct->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $shareProduct->organization_id)->exists();
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

        if (!$shareProduct->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $shareProduct->organization_id)->exists();
    }

    public function delete(User $auth, ShareProduct $shareProduct): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (!$shareProduct->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $shareProduct->organization_id)->exists();
    }
}
