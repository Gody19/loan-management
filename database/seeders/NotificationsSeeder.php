<?php

namespace Database\Seeders;

use App\Models\AiInsight;
use App\Models\AiIntelligenceReport;
use App\Models\AiReportSchedule;
use App\Models\ManagementAction;
use App\Models\User;
use App\Notifications\AiInsightNotification;
use App\Notifications\AiScheduledReportNotification;
use App\Notifications\ManagementActionAssignedNotification;
use Database\Seeders\Concerns\FillsTables;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

class NotificationsSeeder extends Seeder
{
    use FillsTables;

    public function run(): void
    {
        $recipients = User::whereHas('organizations', fn ($query) => $query->where('organizations.id', self::ORG_ID))
            ->orderBy('id')
            ->limit(6)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $this->insightNotifications($recipients);
        $this->reportNotifications($recipients);
        $this->actionNotifications($recipients);

        $this->report('Notifications ready.');
    }

    private function insightNotifications($recipients): void
    {
        $insights = AiInsight::where('organization_id', self::ORG_ID)
            ->whereIn('type', ['overdue_loan', 'portfolio_par', 'accounting_imbalance', 'predictive_outlook'])
            ->orderByDesc('severity')
            ->orderBy('id')
            ->limit(12)
            ->get();

        foreach ($insights as $index => $insight) {
            $recipient = $recipients[$index % $recipients->count()];

            if ($this->has($recipient, AiInsightNotification::class, ['insight_id' => $insight->id])) {
                continue;
            }

            Notification::send($recipient, new AiInsightNotification($insight));

            if ($index % 3 === 0) {
                $recipient->notifications()->update(['read_at' => now()->subDays(fake()->numberBetween(0, 5))]);
            }

            $this->bump('notifications');
        }
    }

    private function reportNotifications($recipients): void
    {
        $schedules = AiReportSchedule::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $reports = AiIntelligenceReport::where('organization_id', self::ORG_ID)
            ->where('status', 'completed')
            ->orderByDesc('generated_at')
            ->get();

        foreach ($schedules as $index => $schedule) {
            $report = $reports->first();

            if (! $report) {
                return;
            }

            $recipient = $recipients[$index % $recipients->count()];

            if ($this->has($recipient, AiScheduledReportNotification::class, ['report_id' => $report->id, 'schedule_id' => $schedule->id])) {
                continue;
            }

            Notification::send($recipient, new AiScheduledReportNotification($schedule, $report, [
                'title' => 'Weekly portfolio digest ready',
                'summary' => 'The scheduled portfolio report for the period is available for review.',
                'narrative' => 'Collections softened slightly while disbursements remained within policy limits.',
            ]));

            if ($index % 2 === 0) {
                $recipient->notifications()->update(['read_at' => now()->subDays(fake()->numberBetween(0, 3))]);
            }

            $this->bump('notifications');
        }
    }

    private function actionNotifications($recipients): void
    {
        $actions = ManagementAction::where('organization_id', self::ORG_ID)
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->limit(14)
            ->get();

        foreach ($actions as $action) {
            $recipient = User::find($action->assigned_to);

            if (! $recipient) {
                continue;
            }

            if ($this->has($recipient, ManagementActionAssignedNotification::class, ['management_action_id' => $action->id])) {
                continue;
            }

            Notification::send($recipient, new ManagementActionAssignedNotification($action));

            if ($action->status->value === 'completed' || $action->status->value === 'cancelled') {
                $recipient->notifications()
                    ->where('type', ManagementActionAssignedNotification::class)
                    ->where('data->management_action_id', $action->id)
                    ->update(['read_at' => now()->subDays(fake()->numberBetween(0, 4))]);
            }

            $this->bump('notifications');
        }
    }

    private function has(User $user, string $type, array $data): bool
    {
        return $user->notifications()
            ->where('type', $type)
            ->where(function ($query) use ($data) {
                foreach ($data as $key => $value) {
                    $query->where('data->'.$key, $value);
                }
            })
            ->exists();
    }
}
