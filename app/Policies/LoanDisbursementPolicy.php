<?php

namespace App\Policies;

use App\Models\LoanDisbursement;
use App\Models\User;

class LoanDisbursementPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('loans.view');
    }

    public function view(User $auth, LoanDisbursement $disbursement): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }
        if (!$disbursement->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $disbursement->organization_id)->exists();
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
