<?php

namespace Database\Seeders;

use App\Enums\ManagementActionPriority;
use App\Enums\ManagementActionStatus;
use App\Models\AiAnomalyFinding;
use App\Models\AiInsight;
use App\Models\AiIntelligenceReport;
use App\Models\AiPrediction;
use App\Models\Branch;
use App\Models\ManagementAction;
use App\Models\ManagementActionEvent;
use App\Models\User;
use Database\Seeders\Concerns\FillsTables;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ManagementActionsSeeder extends Seeder
{
    use FillsTables;

    public function run(): void
    {
        $this->actions();
        $this->report('Management actions ready.');
    }

    private function actions(): void
    {
        if ($this->gap('management_actions') <= 0) {
            return;
        }

        $branches = Branch::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $officers = User::whereHas('organizations', fn ($query) => $query->where('organizations.id', self::ORG_ID))
            ->orderBy('id')
            ->get();

        $insights = AiInsight::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $anomalies = AiAnomalyFinding::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $reports = AiIntelligenceReport::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $predictions = AiPrediction::where('organization_id', self::ORG_ID)->orderBy('id')->get();

        $sources = [];

        foreach ($insights as $insight) {
            $sources[] = [$insight, 'App\Models\AiInsight', 'Insight: '.$insight->title];
        }

        foreach ($anomalies as $anomaly) {
            $sources[] = [$anomaly, 'App\Models\AiAnomalyFinding', 'Finding: '.$anomaly->title];
        }

        foreach ($reports as $report) {
            $sources[] = [$report, 'App\Models\AiIntelligenceReport', 'Report: '.str_replace('_', ' ', $report->report_type->value)];
        }

        foreach ($predictions as $prediction) {
            $sources[] = [$prediction, 'App\Models\AiPrediction', 'Forecast: '.str_replace('_', ' ', $prediction->type->value)];
        }

        if ($sources === []) {
            return;
        }

        $statuses = [
            ManagementActionStatus::Open,
            ManagementActionStatus::Open,
            ManagementActionStatus::InProgress,
            ManagementActionStatus::InProgress,
            ManagementActionStatus::Completed,
            ManagementActionStatus::Cancelled,
        ];

        $titles = [
            'Review and resolve the flagged condition with the member',
            'Follow up on the corrective step and confirm evidence',
            'Escalate to the credit committee for a decision',
            'Schedule a member visit and record the outcome',
            'Reconcile the affected records and close the case',
            'Prepare the corrective summary for the board pack',
        ];

        $descriptions = [
            'Confirm the underlying records, agree the corrective step with the responsible officer and record the outcome before the due date.',
            'Verify that the corrective step has been applied and that no further member follow up is outstanding.',
            'The condition requires a management decision. Gather the supporting figures and present them for approval.',
            'Visit the member, verify the situation in person and document the outcome in the member file.',
            'Reconcile the affected ledger entries, confirm the correction and close the case with a short summary.',
            'Summarise the position, the action taken and any residual risk for the monthly board pack.',
        ];

        foreach ($sources as $index => [$source, $sourceType, $label]) {
            if ($this->gap('management_actions') <= 0) {
                break;
            }

            $status = $statuses[$index % count($statuses)];
            $assignee = $officers[$index % max(1, $officers->count())] ?? null;
            $createdAt = $this->daysAgo(45, 1);
            $dueDate = $createdAt->copy()->addDays(fake()->numberBetween(3, 21));
            $started = in_array($status, [ManagementActionStatus::InProgress, ManagementActionStatus::Completed, ManagementActionStatus::Cancelled], true);
            $completed = $status === ManagementActionStatus::Completed;
            $cancelled = $status === ManagementActionStatus::Cancelled;
            $overdue = $dueDate->isPast() && ! in_array($status, [ManagementActionStatus::Completed, ManagementActionStatus::Cancelled], true);
            $priority = match ($status) {
                ManagementActionStatus::Open => $index % 3 === 0 ? ManagementActionPriority::Urgent : ManagementActionPriority::High,
                ManagementActionStatus::InProgress => $index % 2 === 0 ? ManagementActionPriority::High : ManagementActionPriority::Medium,
                default => ManagementActionPriority::Medium,
            };

            $action = ManagementAction::create([
                'organization_id' => self::ORG_ID,
                'branch_id' => $source->branch_id ?? ($branches[$index % max(1, $branches->count())]?->id),
                'created_by' => self::ACTOR_ID,
                'assigned_to' => $assignee?->id,
                'title' => $titles[$index % count($titles)],
                'description' => $descriptions[$index % count($descriptions)],
                'priority' => $priority,
                'status' => $status,
                'due_date' => $overdue ? $dueDate : $dueDate->copy()->addDays(fake()->numberBetween(5, 30)),
                'started_at' => $started ? $createdAt->copy()->addDay() : null,
                'completed_at' => $completed ? $createdAt->copy()->addDays(fake()->numberBetween(2, 14)) : null,
                'cancelled_at' => $cancelled ? $createdAt->copy()->addDays(fake()->numberBetween(1, 7)) : null,
                'source_type' => $sourceType,
                'source_id' => $source->id,
                'source_label' => $label,
                'completion_notes' => $completed ? 'Verified the corrective step and closed the case with no residual issue.' : null,
                'cancellation_reason' => $cancelled ? 'Superseded by a broader management review covering the same area.' : null,
                'created_at' => $createdAt,
                'updated_at' => $completed || $cancelled ? $createdAt->copy()->addDays(3) : $createdAt,
            ]);

            $this->bump('management_actions');
            $this->timeline($action, $assignee, $status, $createdAt, $overdue);
        }
    }

    private function timeline(ManagementAction $action, ?User $assignee, ManagementActionStatus $status, Carbon $createdAt, bool $overdue): void
    {
        $actor = self::ACTOR_ID;

        $this->event($action, $actor, 'created', null, ManagementActionStatus::Open, null, $assignee?->id, [
            'priority' => $action->priority->value,
            'due_date' => $action->due_date?->toDateString(),
            'source_label' => $action->source_label,
        ], $createdAt);

        if ($assignee) {
            $this->event($action, $actor, 'assigned', ManagementActionStatus::Open, ManagementActionStatus::Open, null, $assignee->id, null, $createdAt->copy()->addHours(2));
        }

        if ($status === ManagementActionStatus::Open) {
            if ($overdue) {
                $this->event($action, $actor, 'updated', ManagementActionStatus::Open, ManagementActionStatus::Open, $assignee?->id, $assignee?->id, [
                    'reason' => 'due_date_passed',
                    'days_past_due' => $action->due_date->diffInDays(now()),
                ], $action->due_date->copy()->addDay());
            }

            return;
        }

        $this->event($action, $assignee?->id ?? $actor, 'started', ManagementActionStatus::Open, ManagementActionStatus::InProgress, $assignee?->id, $assignee?->id, null, $createdAt->copy()->addDay());

        if ($status === ManagementActionStatus::InProgress) {
            if ($overdue) {
                $this->event($action, $assignee?->id ?? $actor, 'updated', ManagementActionStatus::InProgress, ManagementActionStatus::InProgress, $assignee?->id, $assignee?->id, [
                    'reason' => 'due_date_passed',
                    'days_past_due' => $action->due_date->diffInDays(now()),
                ], $action->due_date->copy()->addDay());
            }

            return;
        }

        if ($status === ManagementActionStatus::Completed) {
            $this->event($action, $assignee?->id ?? $actor, 'completed', ManagementActionStatus::InProgress, ManagementActionStatus::Completed, $assignee?->id, $assignee?->id, 'Verified the corrective step and closed the case.', $action->completed_at ?? $createdAt->copy()->addDays(5));

            return;
        }

        $this->event($action, $actor, 'cancelled', ManagementActionStatus::InProgress, ManagementActionStatus::Cancelled, $assignee?->id, $assignee?->id, 'Superseded by a broader management review.', $action->cancelled_at ?? $createdAt->copy()->addDays(4));
    }

    private function event(ManagementAction $action, ?int $actorId, string $event, ?ManagementActionStatus $oldStatus, ?ManagementActionStatus $newStatus, ?int $oldAssignee, ?int $newAssignee, array|string|null $notes, Carbon $createdAt): void
    {
        ManagementActionEvent::create([
            'management_action_id' => $action->id,
            'organization_id' => self::ORG_ID,
            'actor_id' => $actorId ?? self::ACTOR_ID,
            'event' => $event,
            'old_status' => $oldStatus?->value,
            'new_status' => $newStatus?->value,
            'old_assignee_id' => $oldAssignee,
            'new_assignee_id' => $newAssignee,
            'notes' => is_array($notes) ? null : $notes,
            'metadata' => is_array($notes) ? $notes : null,
            'created_at' => $createdAt,
        ]);

        $this->bump('management_action_events');
    }
}
