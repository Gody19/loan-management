<?php

namespace App\AI\Reporting\Scheduling;

use App\AI\DTOs\AiContextData;
use App\AI\Reporting\Services\AiIntelligenceReportService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\ReportPeriodType;
use App\Enums\ReportScheduleRunStatus;
use App\Models\AiIntelligenceReport;
use App\Models\AiReportSchedule;
use App\Models\AiReportScheduleRun;
use App\Models\User;
use App\Notifications\AiScheduledReportNotification;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Throwable;

/**
 * Executes recurring management reports (Phase 12.1).
 *
 * The runner is the only place a schedule produces anything, and it deliberately
 * owns four guarantees:
 *
 *  1. **Authorization is inherited, never widened.** A run executes under a
 *     context *derived from* the schedule's owner and then narrowed to the
 *     schedule's own organization and branch. If that owner has since lost the
 *     reporting capability, the organization or the branch, the run fails with a
 *     clear reason instead of quietly producing a report nobody authorized.
 *  2. **Exactly one execution per schedule and period.** The unique
 *     `(schedule, period)` execution key is claimed *before* any financial work
 *     happens, so a second scheduler tick, a retried command or a manual run
 *     finds the existing run and changes nothing. A schedule can therefore never
 *     publish two reports, or notify twice, for one window.
 *  3. **Failure is isolated and always leaves a record.** A failed generation is
 *     stored as a `failed` run with a safe diagnostic and never as a report; one
 *     broken schedule can never abort the pass over the others.
 *  4. **Delivery is authorized per recipient.** Each recipient's own trusted
 *     context is rebuilt and asked whether it may read *this specific* report
 *     before a notification is created, so a revoked user is never notified.
 */
class AiReportScheduleRunner
{
    public function __construct(
        private readonly AiIntelligenceReportService $reports,
        private readonly AiReportScheduleService $schedules,
        private readonly AiContextBuilderService $contextBuilder,
        private readonly ReportScheduleDigestService $digest,
        private readonly AuditService $audit,
    ) {}

    /**
     * Execute every due schedule. Each schedule is isolated: one failure never
     * stops the pass, and the summary reports both outcomes.
     *
     * @return array<string, int>
     */
    public function runDue(?int $organizationId = null, ?int $limit = null): array
    {
        $limit = min(
            max(1, $limit ?? (int) config('intelligence-reporting.scheduling.max_runs_per_pass', 25)),
            (int) config('intelligence-reporting.scheduling.max_runs_per_pass', 25),
        );

        $schedules = AiReportSchedule::query()
            ->due()
            ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId))
            ->orderBy('next_run_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $summary = [
            'due' => $schedules->count(),
            'completed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'notifications' => 0,
        ];

        foreach ($schedules as $schedule) {
            $run = $this->run($schedule, 'scheduled');

            if ($run->replayed) {
                $summary['skipped']++;
            } else {
                match ($run->status) {
                    ReportScheduleRunStatus::Completed => $summary['completed']++,
                    ReportScheduleRunStatus::Failed => $summary['failed']++,
                    default => $summary['skipped']++,
                };
            }

            $summary['notifications'] += $run->notifications_sent;
        }

        return $summary;
    }

    /**
     * Execute one schedule now.
     *
     * This is the single execution path used by both the scheduler and the
     * dashboard's "Run now" action. A manual run is therefore idempotent in
     * exactly the same way: when the current period has already been executed,
     * the existing run is returned unchanged and nothing is regenerated.
     */
    public function run(AiReportSchedule $schedule, string $trigger = 'scheduled', ?User $requestedBy = null): AiReportScheduleRun
    {
        $period = $schedule->period();

        // Defence in depth: a scheduled report may only cover a period that has
        // fully elapsed, even though the frequency mapping already guarantees it.
        $periodType = ReportPeriodType::tryFrom($period->type);

        if ($periodType === null || ! $periodType->isCompleted()) {
            throw new InvalidArgumentException('A scheduled report may only cover a completed reporting period.');
        }

        $executionKey = AiReportScheduleRun::executionKeyFor(
            (int) $schedule->id,
            $period->type,
            $period->start,
            $period->end,
        );

        // Claim first. The unique index is the real concurrency guard: if another
        // process already claimed this schedule for this period, we return its run
        // untouched instead of generating a second report.
        try {
            $run = AiReportScheduleRun::create([
                'ai_report_schedule_id' => $schedule->id,
                'execution_key' => $executionKey,
                'trigger' => $trigger,
                'status' => ReportScheduleRunStatus::Running->value,
                'period_type' => $period->type,
                'period_start' => $period->start,
                'period_end' => $period->end,
                'timezone' => $schedule->timezone,
                'requested_by' => $requestedBy?->getAuthIdentifier(),
                'started_at' => CarbonImmutable::now(),
            ]);
        } catch (QueryException $exception) {
            return $this->existingRun($schedule, $executionKey, $exception);
        }

        $lock = $this->acquireLock($schedule);

        if ($lock === null) {
            return $this->finishSkipped($run, 'Another execution of this report schedule is already in progress.');
        }

        try {
            $report = $this->generate($schedule);

            $digest = $this->digest->build($schedule, $report);
            $notified = $this->deliver($schedule, $report, $digest);

            $run->update([
                'ai_intelligence_report_id' => $report->id,
                'status' => ReportScheduleRunStatus::Completed->value,
                'notifications_sent' => $notified,
                'digest_available' => (bool) $digest['available'],
                'completed_at' => CarbonImmutable::now(),
            ]);

            $this->advance($schedule);

            $this->audit->log('ai.report_schedule.executed', $schedule, [], [
                'organization_id' => $schedule->organization_id,
                'branch_id' => $schedule->branch_id,
                'report_type' => $schedule->report_type->value,
                'frequency' => $schedule->frequency->value,
                'trigger' => $trigger,
                'execution_key' => $executionKey,
                'period_start' => $period->start,
                'period_end' => $period->end,
                'timezone' => $schedule->timezone,
                'report_id' => $report->id,
                'notifications_sent' => $notified,
                'digest_available' => (bool) $digest['available'],
                'narrative_requested' => (bool) $schedule->include_narrative,
                'requested_by' => $requestedBy?->getAuthIdentifier(),
            ]);

            return $run->refresh();
        } catch (Throwable $exception) {
            return $this->finishFailed($run, $schedule, $trigger, $exception);
        } finally {
            $lock->release();
        }
    }

    /**
     * Produce the report through the Phase 12.0 reporting service, under a
     * context narrowed to the schedule's own scope.
     */
    protected function generate(AiReportSchedule $schedule): AiIntelligenceReport
    {
        $context = $this->executionContext($schedule);

        return $this->reports->generate(
            context: $context,
            user: $this->owner($schedule),
            reportType: $schedule->report_type->value,
            periodType: $schedule->frequency->periodType()->value,
            branchId: $schedule->branch_id,
            withNarrative: (bool) $schedule->include_narrative,
            persist: true,
            // The period is resolved in the schedule's own timezone, so its
            // reporting day never shifts with the server clock.
            asOf: CarbonImmutable::now($schedule->timezone),
        );
    }

    /**
     * The trusted context a scheduled run executes under.
     *
     * It is derived from the schedule owner — the user whose authority justified
     * creating the schedule — and then *narrowed* to the schedule's own
     * organization and branch. Narrowing can only ever remove access, so a run is
     * provably inside both the owner's authority and the schedule's declared
     * scope. When the owner has lost the capability, the organization or the
     * branch, the run fails rather than falling back to a wider context.
     */
    protected function executionContext(AiReportSchedule $schedule): AiContextData
    {
        $owner = $this->owner($schedule);
        $trusted = $this->contextBuilder->build($owner);

        if (! $trusted->hasPermission('ai.reports.view')) {
            throw new InvalidArgumentException('The schedule owner no longer holds the management reporting capability.');
        }

        if (! $trusted->belongsToOrganization((int) $schedule->organization_id)) {
            throw new InvalidArgumentException('The schedule owner no longer belongs to the scheduled organization.');
        }

        if ($schedule->branch_id !== null && ! $trusted->belongsToBranch((int) $schedule->branch_id)) {
            throw new InvalidArgumentException('The schedule owner is no longer assigned to the scheduled branch.');
        }

        return new AiContextData(
            userId: (int) $trusted->userId,
            isSuperAdmin: false,
            roles: $trusted->roles,
            permissions: $trusted->permissions,
            organizationIds: [(int) $schedule->organization_id],
            // An empty branch scope means "the whole authorized organization",
            // which is exactly how the Phase 12.0 reporting service reads it.
            branchIds: $schedule->branch_id !== null ? [(int) $schedule->branch_id] : [],
            vicobaGroupIds: [],
            memberId: $trusted->memberId,
        );
    }

    /**
     * The schedule owner, who is also the run's authorization source. A
     * schedule whose owner no longer exists cannot be executed by anybody, so
     * this is a hard failure rather than a silent system escalation.
     */
    protected function owner(AiReportSchedule $schedule): User
    {
        $owner = User::find($schedule->created_by);

        if ($owner === null) {
            throw new InvalidArgumentException('The schedule owner no longer exists.');
        }

        return $owner;
    }

    /**
     * Deliver the report to the schedule's currently authorized audience.
     *
     * Each candidate recipient is re-authorized against *this specific* report
     * through the same trusted-context read the dashboard uses, so a user who
     * lost access — or who could never read this branch-scoped report — is
     * skipped instead of being told a report exists.
     */
    protected function deliver(AiReportSchedule $schedule, AiIntelligenceReport $report, array $digest): int
    {
        $recipients = $this->schedules->resolveRecipients($schedule);

        if ($recipients->isEmpty()) {
            return 0;
        }

        $cap = (int) config('intelligence-reporting.scheduling.max_recipients_per_run', 50);

        $truncated = 0;

        if ($cap > 0 && $recipients->count() > $cap) {
            $truncated = $recipients->count() - $cap;
            $recipients = $recipients->take($cap);
        }

        $notification = new AiScheduledReportNotification($schedule, $report, $digest);

        $sent = 0;

        foreach ($recipients as $recipient) {
            try {
                $context = $this->contextBuilder->build($recipient);

                // Authorize the recipient against the concrete report before any
                // notification row exists.
                $this->reports->findAuthorized($context, (int) $report->id);
            } catch (Throwable) {
                continue;
            }

            $recipient->notify($notification);
            $sent++;
        }

        if ($truncated > 0) {
            $this->audit->log('ai.report_schedule.recipients_truncated', $schedule, [], [
                'organization_id' => $schedule->organization_id,
                'skipped' => $truncated,
                'cap' => $cap,
            ]);
        }

        return $sent;
    }

    /**
     * Move the schedule forward after an attempt, whether it succeeded or not.
     *
     * The next run is always computed strictly forward in the schedule's own
     * timezone, so a failing schedule retries once per period instead of
     * spinning, and no attempt can ever re-enter a window already consumed.
     */
    protected function advance(AiReportSchedule $schedule): void
    {
        $schedule->update([
            'last_run_at' => CarbonImmutable::now(),
            'next_run_at' => $schedule->frequency->nextRunAfter(
                CarbonImmutable::now($schedule->timezone),
                $schedule->run_time,
                $schedule->weekday,
                $schedule->day_of_month,
            ),
        ]);
    }

    /**
     * The run an earlier execution already claimed. Reported honestly as a skip
     * rather than silently regenerated, so an idempotent retry is visible in the
     * history without duplicating anything.
     */
    protected function existingRun(AiReportSchedule $schedule, string $executionKey, QueryException $exception): AiReportScheduleRun
    {
        $existing = AiReportScheduleRun::where('execution_key', $executionKey)->first();

        if ($existing === null) {
            // A unique violation on something other than the execution key must
            // not be mistaken for an idempotent retry.
            throw $exception;
        }

        $existing->replayed = true;

        $this->audit->log('ai.report_schedule.duplicate_suppressed', $schedule, [], [
            'organization_id' => $schedule->organization_id,
            'execution_key' => $executionKey,
            'existing_run_id' => $existing->id,
            'existing_status' => $existing->status->value,
        ]);

        return $existing;
    }

    protected function finishSkipped(AiReportScheduleRun $run, string $reason): AiReportScheduleRun
    {
        $run->update([
            'status' => ReportScheduleRunStatus::Skipped->value,
            'failure_reason' => $reason,
            'completed_at' => CarbonImmutable::now(),
        ]);

        $schedule = $run->schedule;

        if ($schedule !== null) {
            $this->advance($schedule);
        }

        return $run->refresh();
    }

    /**
     * Record a failure without ever presenting it as a report, and without
     * letting one broken schedule abort the pass over the others.
     */
    protected function finishFailed(AiReportScheduleRun $run, AiReportSchedule $schedule, string $trigger, Throwable $exception): AiReportScheduleRun
    {
        // The diagnostic is the controlled, non-secret validation message when
        // there is one, and a generic sentence otherwise — never an exception
        // message that could carry a connection string or a stack trace.
        $reason = $exception instanceof InvalidArgumentException && $exception->getMessage() !== ''
            ? $exception->getMessage()
            : 'The scheduled report could not be generated.';

        $run->update([
            'status' => ReportScheduleRunStatus::Failed->value,
            'failure_reason' => $reason,
            'completed_at' => CarbonImmutable::now(),
        ]);

        $this->advance($schedule);

        $this->audit->log('ai.report_schedule.failed', $schedule, [], [
            'organization_id' => $schedule->organization_id,
            'branch_id' => $schedule->branch_id,
            'report_type' => $schedule->report_type->value,
            'frequency' => $schedule->frequency->value,
            'trigger' => $trigger,
            'execution_key' => $run->execution_key,
            'run_id' => $run->id,
            'reason' => $reason,
        ]);

        return $run->refresh();
    }

    /**
     * A short-lived advisory lock so two processes never build the same report
     * concurrently. The unique execution key remains the authoritative guard; the
     * lock only avoids duplicated work.
     */
    protected function acquireLock(AiReportSchedule $schedule): ?object
    {
        try {
            $lock = Cache::lock(
                'ai-report-schedule:'.$schedule->id,
                (int) config('intelligence-reporting.scheduling.lock_seconds', 900),
            );

            return $lock->get() ? $lock : null;
        } catch (Throwable) {
            // A cache store without lock support must not stop reporting; the
            // unique execution key still prevents a duplicate.
            return null;
        }
    }
}
