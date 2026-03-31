<?php

use App\Console\Commands\DetectPatterns;
use App\Console\Commands\DispatchScheduledFeatures;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
|
| evolution:detect-patterns  — runs daily to find promotion candidates
| features:dispatch-scheduled — runs every minute to trigger feature executions
|
*/

Schedule::command(DispatchScheduledFeatures::class)->everyMinute();
Schedule::command(DetectPatterns::class)->dailyAt('02:00');
