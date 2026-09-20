<?php

namespace App\Policies;

use App\Models\LoanDisbursement;
use App\Models\User;
use App\Services\OrganizationContext;

class LoanDisbursementPolicy
{
    public function viewAny(User $auth): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }
        return $auth->can('loans.view') || $auth->hasRole('Organization Administrator');
    }

    public function view(User $auth, LoanDisbursement $disbursement): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }
        if (! $auth->can('loans.view') && ! $auth->hasRole('Organization Administrator')) {
            return false;
        }
        if (! $disbursement->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($disbursement->organization_id, $auth);
    }

    public function confirm(User $auth, LoanDisbursement $disbursement): bool
    {
        return $auth->can('loans.create') && $this->view($auth, $disbursement);
    }

    public function reject(User $auth, LoanDisbursement $disbursement): bool
    {
        return $auth->can('loans.create') && $this->view($auth, $disbursement);
    }
}
