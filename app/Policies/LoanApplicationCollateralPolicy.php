<?php

namespace App\Policies;

use App\Models\LoanApplicationCollateral;
use App\Models\User;
use App\Services\OrganizationContext;

class LoanApplicationCollateralPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('loan_application.view');
    }

    public function view(User $auth, LoanApplicationCollateral $collateral): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }
        if (! $collateral->application->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($collateral->application->organization_id, $auth);
    }

    public function manage(User $auth, LoanApplicationCollateral $collateral): bool
    {
        return $auth->can('loan_application.create') && $this->view($auth, $collateral);
    }
}
