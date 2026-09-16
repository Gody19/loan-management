<?php

namespace App\Policies;

use App\Models\LoanApplicationGuarantor;
use App\Models\User;

class LoanApplicationGuarantorPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('loan_application.view');
    }

    public function view(User $auth, LoanApplicationGuarantor $guarantor): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }
        if (!$guarantor->application->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $guarantor->application->organization_id)->exists();
    }

    public function respond(User $auth, LoanApplicationGuarantor $guarantor): bool
    {
        return $guarantor->guarantor_member_id === $auth->member?->id;
    }
}
