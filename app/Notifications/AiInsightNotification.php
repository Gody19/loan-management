<?php

namespace App\Notifications;

use App\Models\AiInsight;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app (database) notification raised when a proactive insight is first
 * created (Phase 11.9). Created by the deterministic generation pipeline, only
 * for users holding ai.insights.view in the affected organization. The payload
 * carries the insight id and display data; the badge on the page links to the
 * Financial Intelligence dashboard.
 */
class AiInsightNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AiInsight $insight,
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
            'insight_id' => $this->insight->id,
            'organization_id' => $this->insight->organization_id,
            'type' => $this->insight->type->value,
            'type_label' => $this->insight->type->label(),
            'severity' => $this->insight->severity->value,
            'severity_label' => $this->insight->severity->label(),
            'title' => $this->insight->title,
            'summary' => $this->insight->summary,
            'url' => route('ai.intelligence.index', absolute: false),
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
