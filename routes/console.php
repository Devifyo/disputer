<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\CheckImapReplies;
use Illuminate\Support\Facades\Schedule;
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::command('imap:check --sync')->everyMinute()->withoutOverlapping();

Schedule::command('cases:alert-escalations')->everyMinute()->withoutOverlapping();

// Trip Protection — poll FlightAware for trips that hit a monitoring checkpoint.
Schedule::command('trips:monitor')->everyFiveMinutes()->withoutOverlapping();

// Nudge claim signers who were invited 48h+ ago and haven't signed.
Schedule::command('claims:signature-reminders')->dailyAt('09:00');

// Claim workflow deadlines (e.g. 30-day airline response timer).
Schedule::command('claims:evaluate-workflow-timers')->dailyAt('08:00');