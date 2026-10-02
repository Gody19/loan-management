<?php

namespace App\AI\ManagementActions\Data;

use App\Models\ManagementAction;

/**
 * An immutable, presentation-safe snapshot of a management action (Phase 12.3).
 *
 * The DTO carries the workflow classification explicitly. A management action
 * is a workflow object — it is deliberately NOT a fact, trend, prediction or
 * advisory, so a consumer can never present a task as an observed financial
 * measurement. When the action references an intelligence source, the source
 * keeps its own original classification and is never re-labelled here.
 */
final class ManagementActionData
{
    public const CLASSIFICATION = 'workflow';

    public const CLASSIFICATION_LABEL = 'Workflow action';

    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly string $statusColor,
        public readonly string $priority,
        public readonly string $priorityLabel,
        public readonly string $priorityColor,
        public readonly ?string $dueDate,
        public readonly string $dueState,
        public readonly string $dueStateLabel,
        public readonly string $dueStateColor,
        public readonly ?string $assigneeName,
        public readonly ?string $creatorName,
        public readonly ?string $organizationName,
        public readonly ?string $branchName,
        public readonly ?string $sourceType,
        public readonly ?int $sourceId,
        public readonly ?string $sourceLabel,
        public readonly bool $overdue,
        public readonly bool $terminal,
        public readonly string $classification,
        public readonly string $classificationLabel,
    ) {}

    public static function fromModel(ManagementAction $action): self
    {
        return new self(
            id: (int) $action->id,
            title: (string) $action->title,
            description: $action->description,
            status: $action->status->value,
            statusLabel: $action->status->label(),
            statusColor: $action->status->color(),
            priority: $action->priority->value,
            priorityLabel: $action->priority->label(),
            priorityColor: $action->priority->color(),
            dueDate: $action->due_date?->toDateString(),
            dueState: $action->dueState(),
            dueStateLabel: $action->dueStateLabel(),
            dueStateColor: $action->dueStateColor(),
            assigneeName: $action->assignee?->fullname ?? $action->assignee?->username,
            creatorName: $action->creator?->fullname ?? $action->creator?->username,
            organizationName: $action->organization?->name,
            branchName: $action->branch?->name,
            sourceType: $action->source_type,
            sourceId: $action->source_id !== null ? (int) $action->source_id : null,
            sourceLabel: $action->source_label,
            overdue: $action->isOverdue(),
            terminal: $action->isTerminal(),
            classification: self::CLASSIFICATION,
            classificationLabel: self::CLASSIFICATION_LABEL,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'status_color' => $this->statusColor,
            'priority' => $this->priority,
            'priority_label' => $this->priorityLabel,
            'priority_color' => $this->priorityColor,
            'due_date' => $this->dueDate,
            'due_state' => $this->dueState,
            'due_state_label' => $this->dueStateLabel,
            'due_state_color' => $this->dueStateColor,
            'assignee_name' => $this->assigneeName,
            'creator_name' => $this->creatorName,
            'organization_name' => $this->organizationName,
            'branch_name' => $this->branchName,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'source_label' => $this->sourceLabel,
            'overdue' => $this->overdue,
            'terminal' => $this->terminal,
            'classification' => $this->classification,
            'classification_label' => $this->classificationLabel,
        ];
    }
}
