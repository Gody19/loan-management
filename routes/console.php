<?php

use App\Models\Repayment;
use App\Notifications\PaymentDueReminder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $dueSoon = Repayment::with('loan.user')
        ->where('status', 'pending')
        ->whereBetween('due_date', [now()->startOfDay(), now()->addWeek()->endOfDay()])
        ->get();

    $sent = 0;
    foreach ($dueSoon as $repayment) {
        if ($repayment->loan && $repayment->loan->user) {
            $repayment->loan->user->notify(new PaymentDueReminder($repayment));
            $sent++;
        }
    }
})->dailyAt('08:00');
