<?php

namespace App\Notifications;

use App\Models\ManagementAction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app (database) notification raised when a management action is assigned to
 * a user (Phase 12.3).
 *
 * This reuses the existing personal inbox infrastructure; it adds no new
 * delivery channel (no email, SMS, WhatsApp, voice or mobile push). The
 * payload only states that a task was assigned — it never carries a financial
 * instruction or an AI conclusion.
 */
class ManagementActionAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ManagementAction $action,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'management_action_id' => $this->action->id,
            'organization_id' => $this->action->organization_id,
            'title' => $this->action->title,
            'priority' => $this->action->priority->value,
            'priority_label' => $this->action->priority->label(),
            'due_date' => $this->action->due_date?->toDateString(),
            'message' => 'You have been assigned a management action.',
            'url' => route('ai.actions.show', $this->action),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
