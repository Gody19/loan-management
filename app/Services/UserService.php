<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        protected AuditService $audit
    ) {}

    /**
     * Create a new user.
     */
    public function create(array $data): User
    {
        $user = User::create([
            'fullname' => $data['fullname'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'nida_number' => $data['nida_number'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'status' => $data['status'] ?? UserStatus::Active,
            'password' => Hash::make($data['password']),
        ]);

        $this->audit->logUserCreated($user);

        return $user;
    }

    /**
     * Update a user.
     */
    public function update(User $user, array $data): User
    {
        $oldData = $user->only([
            'fullname', 'username', 'email', 'phone', 'nida_number',
            'date_of_birth', 'gender', 'status',
        ]);

        $updateData = [
            'fullname' => $data['fullname'] ?? $user->fullname,
            'username' => $data['username'] ?? $user->username,
            'email' => $data['email'] ?? $user->email,
            'phone' => $data['phone'] ?? $user->phone,
            'nida_number' => $data['nida_number'] ?? $user->nida_number,
            'date_of_birth' => $data['date_of_birth'] ?? $user->date_of_birth,
            'gender' => $data['gender'] ?? $user->gender,
            'status' => $data['status'] ?? $user->status,
        ];

        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        $newData = $user->only([
            'fullname', 'username', 'email', 'phone', 'nida_number',
            'date_of_birth', 'gender', 'status',
        ]);

        $this->audit->logUserUpdated($user, $oldData, $newData);

        return $user;
    }

    /**
     * Update user status.
     */
    public function updateStatus(User $user, UserStatus $status): User
    {
        $oldStatus = $user->status->value;
        $user->update(['status' => $status]);

        $this->audit->logUserUpdated(
            $user,
            ['status' => $oldStatus],
            ['status' => $status->value]
        );

        return $user;
    }

    /**
     * Record user login.
     */
    public function recordLogin(User $user): void
    {
        $user->update(['last_login_at' => now()]);
        $this->audit->logLogin($user);
    }

    /**
     * Get filtered query for users, scoped to the given user's organizations.
     */
    public function getFilteredQuery(array $filters, ?User $authUser = null): Builder
    {
        $query = User::query();

        $authUser = $authUser ?? auth()->user();

        // Tenant scoping: non-Super Admin users only see users in their organizations
        if ($authUser && ! $authUser->hasRole('Super Administrator')) {
            $orgIds = $authUser->organizations()->pluck('organizations.id')->toArray();

            if (empty($orgIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('organizations', function ($q) use ($orgIds) {
                    $q->whereIn('organizations.id', $orgIds);
                });
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['role'])) {
            $query->role($filters['role']);
        }

        return $query;
    }
}
