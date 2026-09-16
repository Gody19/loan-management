<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('loans.view');
    }

    public function view(User $auth, Loan $loan): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }
        if (!$loan->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $loan->organization_id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('loans.create');
    }

    public function update(User $auth, Loan $loan): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }
        if (!$loan->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $loan->organization_id)->exists();
    }

    public function disburse(User $auth, Loan $loan): bool
    {
        return $auth->can('loans.create') && $this->view($auth, $loan);
    }

    public function cancel(User $auth, Loan $loan): bool
    {
        return $auth->can('loans.update') && $this->view($auth, $loan);
    }

    public function viewSchedule(User $auth, Loan $loan): bool
    {
        return $auth->can('loans.view') && $this->view($auth, $loan);
    }
}
