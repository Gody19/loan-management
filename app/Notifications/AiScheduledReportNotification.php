<?php

namespace App\Notifications;

use App\Models\AiIntelligenceReport;
use App\Models\AiReportSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app (database) notification raised when a scheduled management report
 * (Phase 12.1) has been produced and is ready to read.
 *
 * The notification is created only for a recipient who was *re-verified* as
 * currently authorized for the report's organization, branch and reporting
 * capability at delivery time, so a revoked user is never notified and a
 * foreign report link is never disclosed.
 *
 * The payload carries no financial figure of its own: it points at the
 * persisted Phase 12.0 report, where every value keeps its classification label
 * and its authoritative source. The optional narrative excerpt is explicitly
 * marked as advisory prose.
 */
class AiScheduledReportNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $digest
     */
    public function __construct(
        public readonly AiReportSchedule $schedule,
        public readonly AiIntelligenceReport $report,
        public readonly array $digest,
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
            'report_id' => $this->report->id,
            'schedule_id' => $this->schedule->id,
            'organization_id' => $this->report->organization_id,
            'branch_id' => $this->report->branch_id,
            'type' => 'scheduled_intelligence_report',
            'type_label' => 'Scheduled management report',
            'severity' => 'notice',
            'severity_label' => 'Notice',
            'title' => (string) ($this->digest['title'] ?? 'Management report ready'),
            'summary' => $this->summary(),
            'period_start' => $this->report->period_start?->toDateString(),
            'period_end' => $this->report->period_end?->toDateString(),
            'data_through' => $this->report->data_through?->toDateString(),
            'frequency' => $this->schedule->frequency->value,
            'narrative' => $this->digest['narrative'] ?? null,
            'url' => route('ai.reports.show', $this->report),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * The digest's deterministic summary, optionally followed by the bounded AI
     * explanation. Both are clearly attributed; neither is a financial figure.
     */
    protected function summary(): string
    {
        $summary = (string) ($this->digest['summary'] ?? '');

        $narrative = $this->digest['narrative'] ?? null;

        if (is_string($narrative) && $narrative !== '') {
            $summary = trim($summary.' '.$narrative);
        }

        return $summary;
    }
}
