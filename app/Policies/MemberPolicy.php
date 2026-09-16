<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('member.view');
    }

    public function view(User $auth, Member $member): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $member->organization_id && $auth->organizations()->where('organizations.id', $member->organization_id)->exists();
        }

        if ($auth->hasRole('Branch Manager')) {
            return $auth->branches()->where('branches.id', $member->branch_id)->exists();
        }

        return ($member->organization_id && $auth->organizations()->where('organizations.id', $member->organization_id)->exists())
            || $auth->branches()->where('branches.id', $member->branch_id)->exists();
    }

    public function create(User $auth): bool
    {
        return $auth->can('member.create');
    }

    public function update(User $auth, Member $member): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $member->organization_id && $auth->organizations()->where('organizations.id', $member->organization_id)->exists();
        }

        if ($auth->hasRole('Branch Manager')) {
            return $auth->branches()->where('branches.id', $member->branch_id)->exists();
        }

        return ($member->organization_id && $auth->organizations()->where('organizations.id', $member->organization_id)->exists())
            || $auth->branches()->where('branches.id', $member->branch_id)->exists();
    }

    public function delete(User $auth, Member $member): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if ($auth->hasRole('Organization Administrator')) {
            return $member->organization_id && $auth->organizations()->where('organizations.id', $member->organization_id)->exists();
        }

        return false;
    }

    public function manageDocuments(User $auth, Member $member): bool
    {
        return $this->view($auth, $member);
    }

    public function manageNextOfKin(User $auth, Member $member): bool
    {
        return $this->view($auth, $member);
    }
}
