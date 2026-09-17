<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class OrganizationContext
{
    /**
     * Get the authenticated user's organization IDs.
     */
    public static function getUserOrganizationIds(?User $user = null): array
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return [];
        }

        return $user->organizations()->pluck('organizations.id')->toArray();
    }

    /**
     * Get organizations query scoped to the authenticated user.
     */
    public static function scopedOrganizations(?User $user = null)
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return Organization::query()->whereRaw('1 = 0');
        }

        if ($user->hasRole('Super Administrator')) {
            return Organization::query();
        }

        $orgIds = self::getUserOrganizationIds($user);

        return Organization::query()->whereIn('organizations.id', $orgIds);
    }

    /**
     * Check if a user belongs to a specific organization.
     */
    public static function userBelongsToOrganization(int $organizationId, ?User $user = null): bool
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('Super Administrator')) {
            return true;
        }

        return $user->organizations()->where('organizations.id', $organizationId)->exists();
    }

    /**
     * Check if $target user shares any organization with $auth user.
     */
    public static function userBelongsToAnyOf(User $target, User $auth): bool
    {
        if ($auth->hasRole('Super Administrator')) {
            return true;
        }

        $authOrgIds = $auth->organizations()->pluck('organizations.id')->toArray();

        if (empty($authOrgIds)) {
            return false;
        }

        return $target->organizations()->whereIn('organizations.id', $authOrgIds)->exists();
    }

    /**
     * Get the first organization of the authenticated user (for contexts requiring a single org).
     */
    public static function getFirstOrganization(?User $user = null): ?Organization
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return null;
        }

        return $user->organizations()->first();
    }

    /**
     * Abort 403 if user does not belong to the given organization.
     */
    public static function authorizeOrganization(int $organizationId, ?User $user = null): void
    {
        if (! self::userBelongsToOrganization($organizationId, $user)) {
            abort(403, 'Unauthorized access to this organization.');
        }
    }

    /**
     * Scope a query to only include records belonging to the user's organizations.
     * Works with any model that has organization_id.
     */
    public static function scopeToUserOrganizations($query, ?User $user = null)
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('Super Administrator')) {
            return $query;
        }

        $orgIds = self::getUserOrganizationIds($user);

        return $query->whereIn('organization_id', $orgIds);
    }

    /**
     * Check if a model's organization_id belongs to the user's organization.
     */
    public static function modelBelongsToUserOrganization($model, ?User $user = null): bool
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('Super Administrator')) {
            return true;
        }

        if (! $model->organization_id) {
            return false;
        }

        return $user->organizations()->where('organizations.id', $model->organization_id)->exists();
    }
}
