<?php

namespace App\AI\ManagementActions\Services;

use App\AI\DTOs\AiContextData;
use App\Models\Branch;
use App\Models\ManagementAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use InvalidArgumentException;

/**
 * The single place that decides who may see, act on and assign a management
 * action (Phase 12.3).
 *
 * Three capabilities govern access, deliberately coarse so the workflow does
 * not proliferate permissions:
 *
 *   ai.actions.view    read the Action Center and an action's timeline
 *   ai.actions.manage  create, edit, start, complete and cancel an action
 *   ai.actions.assign  assign an action to another user
 *
 * Tenant scope is never read from the request. Every lookup is constrained to
 * the acting user's trusted organization set first, so a foreign action is
 * reported as not found rather than disclosing its existence. A user who is
 * branch-limited (has branch assignments) may only see and act on actions
 * inside their branches; a user with organization-wide scope sees the whole
 * organization including organization-wide actions.
 */
class ManagementActionAuthorizationService
{
    public function canView(AiContextData $context): bool
    {
        return $context->hasPermission('ai.actions.view');
    }

    public function canManage(AiContextData $context): bool
    {
        return $context->hasPermission('ai.actions.manage');
    }

    public function canAssign(AiContextData $context): bool
    {
        return $context->hasPermission('ai.actions.assign');
    }

    public function assertCanView(AiContextData $context): void
    {
        if (! $this->canView($context)) {
            throw new InvalidArgumentException('Unauthorized management action access.');
        }
    }

    public function assertCanManage(AiContextData $context): void
    {
        if (! $this->canManage($context)) {
            throw new InvalidArgumentException('Unauthorized management action change.');
        }
    }

    public function assertCanAssign(AiContextData $context): void
    {
        if (! $this->canAssign($context)) {
            throw new InvalidArgumentException('Unauthorized management action assignment.');
        }
    }

    /**
     * A branch-limited user (one with authoritative branch assignments) only
     * reaches actions inside those branches. An organization-wide user (no
     * branch assignments) reaches every branch of their organizations.
     */
    public function applyScope(Builder $query, AiContextData $context): Builder
    {
        $query->forOrganizations($context->organizationIds);

        if ($context->branchIds !== []) {
            $query->whereIn('branch_id', $context->branchIds);
        }

        return $query;
    }

    public function assertWithinScope(AiContextData $context, ManagementAction $action): void
    {
        if (! $context->belongsToOrganization((int) $action->organization_id)) {
            throw new InvalidArgumentException('Unauthorized organization scope.');
        }

        if ($action->branch_id === null) {
            // An organization-wide action is only in scope for a user with
            // organization-wide reach.
            if ($context->branchIds !== []) {
                throw new InvalidArgumentException('Unauthorized branch scope.');
            }

            return;
        }

        if ($context->branchIds !== [] && ! $context->belongsToBranch((int) $action->branch_id)) {
            throw new InvalidArgumentException('Unauthorized branch scope.');
        }
    }

    public function findViewable(AiContextData $context, int $actionId): ManagementAction
    {
        $this->assertCanView($context);

        $action = $this->applyScope(
            ManagementAction::with(['organization', 'branch', 'creator', 'assignee']),
            $context,
        )->whereKey($actionId)->first();

        if ($action === null) {
            throw new InvalidArgumentException('Management action not found.');
        }

        return $action;
    }

    public function findManageable(AiContextData $context, int $actionId): ManagementAction
    {
        $this->assertCanManage($context);

        $action = $this->findViewable($context, $actionId);

        return $action;
    }

    /**
     * Resolve a requested branch inside the acting user's trusted scope. A
     * null/empty value produces an organization-wide action, which is only
     * allowed for a user with organization-wide reach. A branch-limited user
     * must therefore choose one of their own branches.
     */
    public function resolveBranch(AiContextData $context, mixed $value, int $organizationId): ?string
    {
        if ($value === null || $value === '') {
            if ($context->branchIds !== []) {
                throw new InvalidArgumentException('A branch must be selected for your scope.');
            }

            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Unauthorized branch scope.');
        }

        $branch = Branch::find((int) $value);

        if ($branch === null
            || (int) $branch->organization_id !== $organizationId
            || ! $context->belongsToOrganization((int) $branch->organization_id)
            || ! $context->belongsToBranch((int) $branch->id)) {
            throw new InvalidArgumentException('Unauthorized branch scope.');
        }

        return (string) $branch->id;
    }

    /**
     * Resolve an assignee inside the action's organization and (when the action
     * is branch-scoped) branch. The assignee must already hold ai.actions.view
     * so an action can never be assigned to somebody who cannot read it.
     */
    public function resolveAssignee(AiContextData $context, mixed $value, int $organizationId, ?int $branchId): ?User
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('The selected assignee is not authorized for this action.');
        }

        $user = $this->candidateAssignees($organizationId, $branchId)
            ->firstWhere('id', (int) $value);

        if (! $user instanceof User) {
            throw new InvalidArgumentException('The selected assignee is not authorized for this action.');
        }

        return $user;
    }

    /**
     * The users an action may be assigned to: currently authorized readers of
     * the organization (and branch, when scoped) who hold ai.actions.view.
     *
     * @return EloquentCollection<int, User>
     */
    public function candidateAssignees(int $organizationId, ?int $branchId = null): EloquentCollection
    {
        return User::query()
            ->permission('ai.actions.view')
            ->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))
            ->when($branchId !== null, fn ($query) => $query->whereHas('branches', fn ($inner) => $inner->whereKey($branchId)))
            ->orderBy('fullname')
            ->get();
    }
}
