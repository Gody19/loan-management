<?php

namespace App\Policies;

use App\Models\LoanPlan;
use App\Models\User;
use App\Services\OrganizationContext;

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

        if (! $loanPlan->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($loanPlan->organization_id, $auth);
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

        if (! $loanPlan->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($loanPlan->organization_id, $auth);
    }

    public function delete(User $auth, LoanPlan $loanPlan): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $loanPlan->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($loanPlan->organization_id, $auth);
    }
}
