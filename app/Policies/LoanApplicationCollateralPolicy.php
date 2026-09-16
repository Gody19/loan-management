<?php

namespace App\Policies;

use App\Models\LoanApplicationCollateral;
use App\Models\User;

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
        return $auth->organizations()->where('organizations.id', $collateral->application->organization_id)->exists();
    }

    public function manage(User $auth, LoanApplicationCollateral $collateral): bool
    {
        return $auth->can('loan_application.create') && $this->view($auth, $collateral);
    }
}
