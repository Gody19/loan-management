<?php

namespace App\AI\ManagementActions\Services;

use App\AI\DTOs\AiContextData;
use App\Enums\ManagementActionPriority;
use App\Enums\ManagementActionStatus;
use App\Models\AiAnomalyFinding;
use App\Models\AiInsight;
use App\Models\AiIntelligenceReport;
use App\Models\AiPrediction;
use App\Models\ManagementAction;
use App\Models\ManagementActionEvent;
use App\Models\User;
use App\Notifications\ManagementActionAssignedNotification;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Owns the management action lifecycle (Phase 12.3): creation, editing,
 * assignment, start, completion and cancellation, each audited and recorded in
 * the append-only timeline.
 *
 * Three invariants are enforced here rather than in the controller:
 *
 *  1. **Humans only, no automation.** Nothing in this service reacts to an AI
 *     output: an action is only ever created, assigned, started, completed or
 *     cancelled by an explicit, capability-checked human request.
 *  2. **No privilege escalation.** The organization always comes from the
 *     trusted context, a branch is re-validated against it and an assignee must
 *     already be an authorized reader inside the action's own scope.
 *  3. **Terminal states are immutable.** A completed or cancelled action can
 *     never be reopened, reassigned or edited.
 */
class ManagementActionService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ManagementActionAuthorizationService $authorization,
    ) {}

    /**
     * Create a management action inside the acting user's authorized scope.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(AiContextData $context, User $user, array $attributes): ManagementAction
    {
        $this->authorization->assertCanManage($context);

        $organizationId = $this->resolveOrganization($context);
        $branchId = $this->authorization->resolveBranch($context, $attributes['branch_id'] ?? null, $organizationId);
        $title = $this->normalizeTitle($attributes['title'] ?? null);
        $description = $this->normalizeDescription($attributes['description'] ?? null);
        $priority = $this->resolvePriority($attributes['priority'] ?? null, true);
        $dueDate = $this->normalizeDueDate($attributes['due_date'] ?? null);
        $source = $this->resolveSource($context, $attributes, $organizationId);

        $assignee = null;

        if (array_key_exists('assigned_to', $attributes) && $attributes['assigned_to'] !== null && $attributes['assigned_to'] !== '') {
            // Assigning to somebody else is a management act and requires the
            // assign capability; self-assignment never escalates anything.
            if ((int) $attributes['assigned_to'] !== $context->userId) {
                $this->authorization->assertCanAssign($context);
            }

            $assignee = $this->authorization->resolveAssignee(
                $context,
                $attributes['assigned_to'],
                $organizationId,
                $branchId !== null ? (int) $branchId : null,
            );
        }

        $action = DB::transaction(function () use ($organizationId, $branchId, $title, $description, $priority, $dueDate, $source, $assignee, $user) {
            $action = ManagementAction::create([
                'organization_id' => $organizationId,
                'branch_id' => $branchId,
                'created_by' => $user->getAuthIdentifier(),
                'assigned_to' => $assignee?->getAuthIdentifier(),
                'title' => $title,
                'description' => $description,
                'priority' => $priority->value,
                'status' => ManagementActionStatus::Open->value,
                'due_date' => $dueDate,
                'source_type' => $source['source_type'],
                'source_id' => $source['source_id'],
                'source_label' => $source['source_label'],
            ]);

            $this->recordEvent($action, $user, 'created', null, ManagementActionStatus::Open, null, $assignee?->getAuthIdentifier(), null, [
                'priority' => $priority->value,
                'due_date' => $dueDate,
                'source_type' => $source['source_type'],
                'source_id' => $source['source_id'],
            ]);

            return $action;
        });

        $this->audit->log('ai.management_action.created', $action, [], [
            'organization_id' => $action->organization_id,
            'branch_id' => $action->branch_id,
            'priority' => $action->priority->value,
            'status' => $action->status->value,
            'assigned_to' => $action->assigned_to,
            'source_type' => $action->source_type,
            'source_id' => $action->source_id,
            'due_date' => $action->due_date?->toDateString(),
        ]);

        $this->notifyAssignee($action, $assignee, $user);

        return $action;
    }

    /**
     * Edit the human-editable metadata of an open action (title, description,
     * priority, due date). Assignment is a separate, separately-authorized act.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(AiContextData $context, User $user, ManagementAction $action, array $attributes): ManagementAction
    {
        $this->authorization->assertCanManage($context);
        $this->authorization->assertWithinScope($context, $action);
        $this->assertMutable($action);

        $before = $action->only(['title', 'description', 'priority', 'due_date']);

        $action->update([
            'title' => array_key_exists('title', $attributes) ? $this->normalizeTitle($attributes['title']) : $action->title,
            'description' => array_key_exists('description', $attributes) ? $this->normalizeDescription($attributes['description']) : $action->description,
            'priority' => array_key_exists('priority', $attributes) ? $this->resolvePriority($attributes['priority'], false)->value : $action->priority->value,
            'due_date' => array_key_exists('due_date', $attributes) ? $this->normalizeDueDate($attributes['due_date']) : $action->due_date,
        ]);

        $this->recordEvent($action, $user, 'updated', $action->status, $action->status, $action->assigned_to, $action->assigned_to, null, [
            'before' => $this->presentable($before),
            'after' => $this->presentable($action->only(['title', 'description', 'priority', 'due_date'])),
        ]);

        $this->audit->log('ai.management_action.updated', $action, $before, [
            'title' => $action->title,
            'priority' => $action->priority->value,
            'due_date' => $action->due_date?->toDateString(),
        ]);

        return $action;
    }

    /**
     * Assign (or unassign) an open action. Requires the assign capability.
     */
    public function assign(AiContextData $context, User $user, ManagementAction $action, mixed $assigneeId): ManagementAction
    {
        $this->authorization->assertCanAssign($context);
        $this->authorization->assertWithinScope($context, $action);
        $this->assertMutable($action);

        $assignee = $this->authorization->resolveAssignee(
            $context,
            $assigneeId,
            (int) $action->organization_id,
            $action->branch_id !== null ? (int) $action->branch_id : null,
        );

        $previous = $action->assigned_to;

        if ((int) ($previous ?? 0) === (int) ($assignee?->getAuthIdentifier() ?? 0)) {
            return $action;
        }

        $action->update(['assigned_to' => $assignee?->getAuthIdentifier()]);

        $this->recordEvent($action, $user, 'assigned', $action->status, $action->status, $previous, $assignee?->getAuthIdentifier());

        $this->audit->log('ai.management_action.assigned', $action, [
            'assigned_to' => $previous,
        ], [
            'assigned_to' => $assignee?->getAuthIdentifier(),
        ]);

        $this->notifyAssignee($action, $assignee, $user);

        return $action;
    }

    /**
     * Move an open action into progress. Only the human move is recorded.
     */
    public function start(AiContextData $context, User $user, ManagementAction $action): ManagementAction
    {
        $this->authorization->assertCanManage($context);
        $this->authorization->assertWithinScope($context, $action);
        $this->assertTransition($action, ManagementActionStatus::InProgress);

        $previous = $action->status;

        $action->update([
            'status' => ManagementActionStatus::InProgress->value,
            'started_at' => $action->started_at ?? now(),
        ]);

        $this->recordEvent($action, $user, 'started', $previous, ManagementActionStatus::InProgress, $action->assigned_to, $action->assigned_to);

        $this->audit->log('ai.management_action.started', $action, [
            'status' => $previous->value,
        ], [
            'status' => $action->status->value,
        ]);

        return $action;
    }

    /**
     * Complete an open or in-progress action.
     */
    public function complete(AiContextData $context, User $user, ManagementAction $action, ?string $notes = null): ManagementAction
    {
        $this->authorization->assertCanManage($context);
        $this->authorization->assertWithinScope($context, $action);
        $this->assertTransition($action, ManagementActionStatus::Completed);

        $previous = $action->status;

        $action->update([
            'status' => ManagementActionStatus::Completed->value,
            'completed_at' => now(),
            'completion_notes' => $this->normalizeText($notes, 2000),
        ]);

        $this->recordEvent($action, $user, 'completed', $previous, ManagementActionStatus::Completed, $action->assigned_to, $action->assigned_to, $this->normalizeText($notes, 2000));

        $this->audit->log('ai.management_action.completed', $action, [
            'status' => $previous->value,
        ], [
            'status' => $action->status->value,
            'completed_at' => $action->completed_at?->toDateTimeString(),
        ]);

        return $action;
    }

    /**
     * Cancel an open or in-progress action.
     */
    public function cancel(AiContextData $context, User $user, ManagementAction $action, ?string $reason = null): ManagementAction
    {
        $this->authorization->assertCanManage($context);
        $this->authorization->assertWithinScope($context, $action);
        $this->assertTransition($action, ManagementActionStatus::Cancelled);

        $previous = $action->status;

        $action->update([
            'status' => ManagementActionStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancellation_reason' => $this->normalizeText($reason, 2000),
        ]);

        $this->recordEvent($action, $user, 'cancelled', $previous, ManagementActionStatus::Cancelled, $action->assigned_to, $action->assigned_to, $this->normalizeText($reason, 2000));

        $this->audit->log('ai.management_action.cancelled', $action, [
            'status' => $previous->value,
        ], [
            'status' => $action->status->value,
            'cancelled_at' => $action->cancelled_at?->toDateTimeString(),
        ]);

        return $action;
    }

    /**
     * The acting user's own authoritative organization. The request can never
     * supply this.
     */
    protected function resolveOrganization(AiContextData $context): int
    {
        if ($context->organizationIds === []) {
            throw new InvalidArgumentException('No authorized organization for a management action.');
        }

        return (int) $context->organizationIds[0];
    }

    protected function assertMutable(ManagementAction $action): void
    {
        if ($action->isTerminal()) {
            throw new InvalidArgumentException('A completed or cancelled management action is immutable.');
        }
    }

    protected function assertTransition(ManagementAction $action, ManagementActionStatus $target): void
    {
        if (! $action->status->canTransitionTo($target)) {
            throw new InvalidArgumentException(sprintf(
                'A %s management action cannot be moved to %s.',
                mb_strtolower($action->status->label()),
                mb_strtolower($target->label()),
            ));
        }
    }

    protected function resolvePriority(mixed $value, bool $default): ManagementActionPriority
    {
        if ($value === null || $value === '') {
            if ($default) {
                return ManagementActionPriority::Medium;
            }

            throw new InvalidArgumentException('Unsupported management action priority.');
        }

        $priority = is_string($value) ? ManagementActionPriority::tryFrom($value) : null;

        if ($priority === null) {
            throw new InvalidArgumentException('Unsupported management action priority.');
        }

        return $priority;
    }

    protected function normalizeTitle(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('A management action requires a title.');
        }

        return mb_substr(trim($value), 0, 160);
    }

    protected function normalizeDescription(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('The management action description must be text.');
        }

        return mb_substr(trim($value), 0, 4000);
    }

    protected function normalizeText(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('The supplied note must be text.');
        }

        return mb_substr(trim($value), 0, $max);
    }

    protected function normalizeDueDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('The due date must be a valid date.');
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            throw new InvalidArgumentException('The due date must be a valid date.');
        }
    }

    /**
     * Resolve and validate an optional source artifact. The source must exist
     * and belong to the action's organization, so an action can never point at
     * another tenant's insight, prediction, report or finding.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{source_type: ?string, source_id: ?int, source_label: ?string}
     */
    protected function resolveSource(AiContextData $context, array $attributes, int $organizationId): array
    {
        $alias = $attributes['source_type'] ?? null;
        $sourceId = $attributes['source_id'] ?? null;

        if (($alias === null || $alias === '') && ($sourceId === null || $sourceId === '')) {
            return ['source_type' => null, 'source_id' => null, 'source_label' => null];
        }

        if (! is_string($alias) || ! array_key_exists($alias, ManagementAction::SOURCE_TYPES)) {
            throw new InvalidArgumentException('Unsupported management action source type.');
        }

        if (! is_numeric($sourceId)) {
            throw new InvalidArgumentException('A management action source requires a valid record id.');
        }

        $class = ManagementAction::SOURCE_TYPES[$alias];
        $model = $class::query()->whereKey((int) $sourceId)->first();

        if ($model === null) {
            throw new InvalidArgumentException('The selected source record does not exist.');
        }

        if ((int) $model->organization_id !== $organizationId
            || ! $context->belongsToOrganization((int) $model->organization_id)) {
            throw new InvalidArgumentException('The selected source record belongs to another organization.');
        }

        return [
            'source_type' => $class,
            'source_id' => (int) $sourceId,
            'source_label' => $this->sourceLabel($class, $model),
        ];
    }

    protected function sourceLabel(string $class, object $model): ?string
    {
        return match ($class) {
            AiInsight::class => (string) $model->title,
            AiPrediction::class => $model->type->label().' outlook',
            AiIntelligenceReport::class => $model->report_type->label(),
            AiAnomalyFinding::class => (string) ($model->title ?? $model->type->label()),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    protected function recordEvent(
        ManagementAction $action,
        ?User $actor,
        string $event,
        ?ManagementActionStatus $oldStatus,
        ?ManagementActionStatus $newStatus,
        ?int $oldAssignee,
        ?int $newAssignee,
        ?string $notes = null,
        ?array $metadata = null,
    ): void {
        ManagementActionEvent::create([
            'management_action_id' => $action->id,
            'organization_id' => $action->organization_id,
            'actor_id' => $actor?->getAuthIdentifier(),
            'event' => $event,
            'old_status' => $oldStatus?->value,
            'new_status' => $newStatus?->value,
            'old_assignee_id' => $oldAssignee,
            'new_assignee_id' => $newAssignee,
            'notes' => $notes,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    protected function notifyAssignee(ManagementAction $action, ?User $assignee, User $actor): void
    {
        if ($assignee === null || (int) $assignee->getAuthIdentifier() === (int) $actor->getAuthIdentifier()) {
            return;
        }

        $assignee->notify(new ManagementActionAssignedNotification($action));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function presentable(array $values): array
    {
        $priority = $values['priority'] ?? null;

        return [
            'title' => $values['title'] ?? null,
            'priority' => $priority instanceof ManagementActionPriority ? $priority->value : $priority,
            'due_date' => isset($values['due_date']) && $values['due_date'] !== null
                ? (string) $values['due_date']
                : null,
        ];
    }
}
