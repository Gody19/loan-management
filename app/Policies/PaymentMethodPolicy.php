<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('payment_method.view');
    }

    public function view(User $auth, PaymentMethod $paymentMethod): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (!$paymentMethod->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $paymentMethod->organization_id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('payment_method.create');
    }

    public function update(User $auth, PaymentMethod $paymentMethod): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (!$paymentMethod->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $paymentMethod->organization_id)->exists();
    }

    public function delete(User $auth, PaymentMethod $paymentMethod): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (!$paymentMethod->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $paymentMethod->organization_id)->exists();
    }
}
