<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Production requires ONE scheduler process. Run:
 *   php artisan schedule:work            (foreground, supervised)
 * or
 *   * * * * * cd /path/to/financepro && php artisan schedule:run >> /dev/null 2>&1
 *
 * on a single host, or use ->onOneServer() below, which requires a shared cache
 * store (redis or memcached, NOT the default database cache) so that a second
 * scheduler node cannot also fire these jobs.
 */

// Writes financial data (marks loans delinquent). A second concurrent pass would
// double-mark, so it must never overlap itself.
Schedule::command('loans:update-delinquency')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->onOneServer();

// Anonymous landing-page conversations have a bounded life: the retention
// window removes them (and their messages) daily so public chats are never
// kept indefinitely. Private member conversations are never affected.
Schedule::command('ai:cleanup-public-conversations')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// Predictive Intelligence (Phase 11.8): a scheduled, audited daily snapshot of
// the statistical outlooks for every organization. Idempotent per snapshot.
Schedule::command('ai:refresh-predictions')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Proactive Intelligence (Phase 11.9): a scheduled, audited daily generation
// pass of deterministic insights and alerts for every organization.
// Idempotent per dedup key; retires insights whose condition stopped holding.
// This one pushes in-app notifications, so a duplicated pass means staff read the
// same alert twice.
Schedule::command('ai:generate-insights')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer();

// Scheduled Management Reporting (Phase 12.1): produces the due recurring
// management intelligence reports and delivers them to their authorized
// audience. The pass runs hourly because a schedule may be daily, weekly,
// monthly or quarterly in any timezone, and the runner claims each
// schedule+period exactly once — so a tick that finds nothing due does nothing.
Schedule::command('ai:run-report-schedules')
    ->hourlyAt('05')
    ->withoutOverlapping()
    ->onOneServer();
