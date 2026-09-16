<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\User;

class LoanApplicationPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('loan_application.view');
    }

    public function view(User $auth, LoanApplication $application): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }
        if (!$application->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $application->organization_id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('loan_application.create');
    }

    public function update(User $auth, LoanApplication $application): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }
        if (!$application->organization_id) {
            return false;
        }
        return $auth->organizations()->where('organizations.id', $application->organization_id)->exists();
    }

    public function submit(User $auth, LoanApplication $application): bool
    {
        return $auth->can('loan_application.create') && $this->view($auth, $application);
    }

    public function cancel(User $auth, LoanApplication $application): bool
    {
        return $auth->can('loan_application.create') && $this->view($auth, $application);
    }

    public function review(User $auth, LoanApplication $application): bool
    {
        return $auth->can('loan_application.approve') && $this->view($auth, $application);
    }

    public function approve(User $auth, LoanApplication $application): bool
    {
        return $auth->can('loan_application.approve') && $this->view($auth, $application);
    }

    public function reject(User $auth, LoanApplication $application): bool
    {
        return $auth->can('loan_application.approve') && $this->view($auth, $application);
    }

    public function manageGuarantors(User $auth, LoanApplication $application): bool
    {
        return $auth->can('loan_application.create') && $this->view($auth, $application);
    }

    public function manageCollateral(User $auth, LoanApplication $application): bool
    {
        return $auth->can('loan_application.create') && $this->view($auth, $application);
    }
}
