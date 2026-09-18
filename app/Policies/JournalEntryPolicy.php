<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;
use App\Services\OrganizationContext;

class JournalEntryPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('accounting.view');
    }

    public function view(User $auth, JournalEntry $entry): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $entry->organization_id) {
            return false;
        }

        return OrganizationContext::userBelongsToOrganization($entry->organization_id, $auth);
    }

    public function create(User $auth): bool
    {
        return $auth->can('accounting.create');
    }

    public function update(User $auth, JournalEntry $entry): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $entry->organization_id) {
            return false;
        }

        return OrganizationContext::userBelongsToOrganization($entry->organization_id, $auth);
    }

    public function delete(User $auth, JournalEntry $entry): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        if (! $entry->organization_id) {
            return false;
        }

        return OrganizationContext::userBelongsToOrganization($entry->organization_id, $auth);
    }
}
