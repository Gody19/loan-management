<?php

namespace App\Policies;

use App\Models\LoanRepayment;
use App\Models\User;

class LoanRepaymentPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('loan-repayments.view');
    }

    public function view(User $auth, LoanRepayment $repayment): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }
        if (!$repayment->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $repayment->organization_id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('loan-repayments.create');
    }

    public function reverse(User $auth, LoanRepayment $repayment): bool
    {
        return $auth->can('loan-repayments.update') && $this->view($auth, $repayment);
    }

    public function viewStatement(User $auth, $loan): bool
    {
        return $auth->can('loans.view');
    }
}
