<?php

namespace App\Policies;

use App\Models\LoanApprovalLevel;
use App\Models\User;
use App\Services\OrganizationContext;

class LoanApprovalLevelPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('loan_approval_level.view');
    }

    public function view(User $auth, LoanApprovalLevel $level): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }
        if (! $level->organization_id) {
            return false;
        }
        return OrganizationContext::userBelongsToOrganization($level->organization_id, $auth);
    }

    public function create(User $auth): bool
    {
        return $auth->can('loan_approval_level.create');
    }

    public function update(User $auth, LoanApprovalLevel $level): bool
    {
        return $auth->can('loan_approval_level.update') && $this->view($auth, $level);
    }

    public function delete(User $auth, LoanApprovalLevel $level): bool
    {
        return $auth->can('loan_approval_level.delete') && $this->view($auth, $level);
    }
}
