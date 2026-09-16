<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    public function create(array $data): Organization
    {
        return Organization::create($data);
    }

    /**
     * Create an organization with its initial administrator atomically.
     */
    public function createWithAdmin(array $orgData, ?array $adminData = null, ?int $assignUserId = null): Organization
    {
        return DB::transaction(function () use ($orgData, $adminData, $assignUserId) {
            $organization = Organization::create($orgData);

            if ($adminData) {
                $user = User::create([
                    'fullname' => $adminData['admin_name'],
                    'email' => $adminData['admin_email'],
                    'phone' => $adminData['admin_phone'] ?? null,
                    'username' => $adminData['admin_email'],
                    'nida_number' => strtoupper(uniqid('NIDA')),
                    'password' => bcrypt($adminData['admin_password']),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                $user->assignRole('Organization Administrator');
                $organization->users()->syncWithoutDetaching($user->id);
            } elseif ($assignUserId) {
                $user = User::findOrFail($assignUserId);
                $organization->users()->syncWithoutDetaching($user->id);

                if (! $user->hasRole('Organization Administrator')) {
                    $user->assignRole('Organization Administrator');
                }
            }

            return $organization;
        });
    }

    public function update(Organization $organization, array $data): Organization
    {
        $organization->update($data);

        return $organization;
    }

    public function getFilteredQuery(array $filters): Builder
    {
        $query = Organization::query();

        return $this->applyFilters($query, $filters);
    }

    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%")
                    ->orWhere('district', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    public function getForUser($user): Builder
    {
        $query = Organization::query();

        if (! $user->hasRole('Super Administrator')) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query;
    }

    /**
     * Assign an existing user as Organization Administrator.
     */
    public function assignAdmin(Organization $organization, int $userId): void
    {
        $user = User::findOrFail($userId);
        $organization->users()->syncWithoutDetaching($userId);

        if (! $user->hasRole('Organization Administrator')) {
            $user->assignRole('Organization Administrator');
        }
    }

    /**
     * Remove an administrator assignment from an organization.
     */
    public function removeAdmin(Organization $organization, int $userId): void
    {
        $adminCount = $organization->users()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Organization Administrator'))
            ->count();

        if ($adminCount <= 1) {
            throw new \Exception('Cannot remove the last administrator. An organization must have at least one administrator.');
        }

        $organization->users()->detach($userId);
    }

    /**
     * Get administrators for an organization.
     */
    public function getAdministrators(Organization $organization)
    {
        return $organization->users()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Organization Administrator'));
    }
}
