<?php

namespace App\Policies;

use App\Models\LoanPlan;
use App\Models\User;

class LoanPlanPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('loan_plan.view');
    }

    public function view(User $auth, LoanPlan $loanPlan): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->organizations()->where('organizations.id', $loanPlan->organization_id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('loan_plan.create');
    }

    public function update(User $auth, LoanPlan $loanPlan): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->organizations()->where('organizations.id', $loanPlan->organization_id)->exists();
    }

    public function delete(User $auth, LoanPlan $loanPlan): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        return $auth->organizations()->where('organizations.id', $loanPlan->organization_id)->exists();
    }
}
