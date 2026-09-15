<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;

class BranchService
{
    public function create(array $data): Branch
    {
        return Branch::create($data);
    }

    public function update(Branch $branch, array $data): Branch
    {
        $branch->update($data);
        return $branch;
    }

    public function getFilteredQuery(array $filters): Builder
    {
        $query = Branch::with('organization');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('manager', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        return $query;
    }

    public function getForUser($user): Builder
    {
        $query = Branch::with('organization');

        if (!$user->hasRole('Super Administrator')) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('users', fn ($uq) => $uq->where('users.id', $user->id))
                  ->orWhereHas('organization.users', fn ($uq) => $uq->where('users.id', $user->id));
            });
        }

        return $query;
    }
}
