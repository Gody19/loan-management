<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('loans:update-delinquency')->dailyAt('01:00');

// Anonymous landing-page conversations have a bounded life: the retention
// window removes them (and their messages) daily so public chats are never
// kept indefinitely. Private member conversations are never affected.
Schedule::command('ai:cleanup-public-conversations')->dailyAt('02:00');

// Predictive Intelligence (Phase 11.8): a scheduled, audited daily snapshot of
// the statistical outlooks for every organization. Idempotent per snapshot.
Schedule::command('ai:refresh-predictions')->dailyAt('03:00');

// Proactive Intelligence (Phase 11.9): a scheduled, audited daily generation
// pass of deterministic insights and alerts for every organization.
// Idempotent per dedup key; retires insights whose condition stopped holding.
Schedule::command('ai:generate-insights')->dailyAt('04:00');
