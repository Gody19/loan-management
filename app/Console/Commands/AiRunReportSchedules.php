<?php

namespace App\Console\Commands;

use App\AI\Reporting\Scheduling\AiReportScheduleRunner;
use Illuminate\Console\Command;

/**
 * Runs the due recurring management reports (Phase 12.1).
 *
 * The command is deliberately thin: every decision — which schedules are due,
 * what period they cover, whether a run may proceed, who receives the report —
 * belongs to AiReportScheduleRunner, so the scheduled pass and the dashboard's
 * "Run now" action can never diverge.
 *
 * The pass is idempotent per schedule and period, so running it a second time in
 * the same minute (a retried cron, a manual invocation, a second worker) produces
 * nothing new: the already-claimed executions are suppressed and reported as
 * skips.
 */
class AiRunReportSchedules extends Command
{
    protected $signature = 'ai:run-report-schedules
                            {--organization= : Only run schedules for this organization id}
                            {--limit= : Maximum number of due schedules to run in this pass}';

    protected $description = 'Run due scheduled management intelligence reports';

    public function handle(AiReportScheduleRunner $runner): int
    {
        if (! (bool) config('intelligence-reporting.scheduling.enabled', true)) {
            $this->warn('Scheduled management reporting is disabled.');

            return self::SUCCESS;
        }

        $organizationId = $this->option('organization');
        $limit = $this->option('limit');

        $summary = $runner->runDue(
            $organizationId !== null ? (int) $organizationId : null,
            $limit !== null ? (int) $limit : null,
        );

        if ($summary['due'] === 0) {
            $this->info('No scheduled management reports are due.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Scheduled reports: %d due, %d completed, %d failed, %d skipped, %d notification(s).',
            $summary['due'],
            $summary['completed'],
            $summary['failed'],
            $summary['skipped'],
            $summary['notifications'],
        ));

        // A failing schedule is recorded and isolated; it must not make the
        // scheduler treat the whole pass as a failure and retry it endlessly.
        return self::SUCCESS;
    }
}
