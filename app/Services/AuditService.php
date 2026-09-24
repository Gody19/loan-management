<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /**
     * Log an audit event.
     */
    public function log(string $event, ?Model $model = null, array $old = [], array $new = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'organization_id' => $this->resolveOrganizationId($model),
            'event' => $event,
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model?->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Resolve the organization an audit event belongs to.
     *
     * Uses the auditable model's organization where available, falling back
     * to the acting user when they belong to exactly one organization.
     * Returns null for platform-level or ambiguous events.
     */
    protected function resolveOrganizationId(?Model $model): ?int
    {
        if ($model instanceof Organization) {
            return $model->id;
        }

        if ($model && $model->organization_id !== null) {
            return (int) $model->organization_id;
        }

        $user = $model instanceof User
            ? $model
            : User::find(auth()->id());

        if (! $user) {
            return null;
        }

        $orgIds = $user->organizations()->pluck('organizations.id')->all();

        return count($orgIds) === 1 ? (int) reset($orgIds) : null;
    }

    /**
     * Log user login.
     */
    public function logLogin(User $user): AuditLog
    {
        return $this->log('user.login', $user, [], [
            'email' => $user->email,
            'last_login_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log user logout.
     */
    public function logLogout(User $user): AuditLog
    {
        return $this->log('user.logout', $user);
    }

    /**
     * Log user creation.
     */
    public function logUserCreated(User $user): AuditLog
    {
        return $this->log('user.created', $user, [], $user->only([
            'fullname', 'username', 'email', 'phone', 'status',
        ]));
    }

    /**
     * Log user update.
     */
    public function logUserUpdated(User $user, array $old, array $new): AuditLog
    {
        return $this->log('user.updated', $user, $old, $new);
    }

    /**
     * Log user deletion.
     */
    public function logUserDeleted(User $user): AuditLog
    {
        return $this->log('user.deleted', $user, $user->only([
            'fullname', 'username', 'email',
        ]), []);
    }

    /**
     * Log role assignment.
     */
    public function logRoleAssigned(User $user, string $role): AuditLog
    {
        return $this->log('role.assigned', $user, [], ['role' => $role]);
    }

    /**
     * Log role removal.
     */
    public function logRoleRemoved(User $user, string $role): AuditLog
    {
        return $this->log('role.removed', $user, ['role' => $role], []);
    }

    /**
     * Log permission change.
     */
    public function logPermissionChanged(User $user, string $permission, string $action): AuditLog
    {
        return $this->log("permission.{$action}", $user, [], ['permission' => $permission]);
    }

    /**
     * Log password reset.
     */
    public function logPasswordReset(User $user): AuditLog
    {
        return $this->log('user.password_reset', $user);
    }
}
