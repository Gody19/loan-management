<?php

namespace App\Policies;

use App\Models\LoanApplicationGuarantor;
use App\Models\User;
use App\Services\OrganizationContext;

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
        if (! $guarantor->application->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($guarantor->application->organization_id, $auth);
    }

    public function respond(User $auth, LoanApplicationGuarantor $guarantor): bool
    {
        return $guarantor->guarantor_member_id === $auth->member?->id;
    }
}
