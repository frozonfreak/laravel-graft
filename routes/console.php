<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
|
| GraftAI module schedules are defined in:
| Modules/GraftAI/app/Providers/GraftAIServiceProvider::configureSchedules()
|
|   evolution:detect-patterns    — daily at 02:00
|   features:dispatch-scheduled  — every minute
|
*/
