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
