<?php

namespace App\Services;

use App\Models\VicobaGroup;
use Illuminate\Database\Eloquent\Builder;

class VicobaGroupService
{
    public function create(array $data): VicobaGroup
    {
        return VicobaGroup::create($data);
    }

    public function update(VicobaGroup $group, array $data): VicobaGroup
    {
        $group->update($data);

        return $group;
    }

    public function getFilteredQuery(array $filters): Builder
    {
        $query = VicobaGroup::with('branch.organization');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('meeting_location', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        return $query;
    }

    public function getForUser($user): Builder
    {
        $query = VicobaGroup::with('branch.organization');

        if (! $user->hasRole('Super Administrator')) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('branch.users', fn ($uq) => $uq->where('users.id', $user->id))
                    ->orWhereHas('branch.organization.users', fn ($uq) => $uq->where('users.id', $user->id));
            });
        }

        return $query;
    }
}
