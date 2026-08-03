<?php

use App\Jobs\CheckProviderWalletsJob;
use App\Jobs\CleanupMonitoringDataJob;
use App\Jobs\ExpirePendingApprovalsJob;
use App\Jobs\RemindOpenSettlementsJob;
use App\Jobs\RetryFailedNotificationsJob;
use App\Jobs\RunSystemHealthProbesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RunSystemHealthProbesJob)->everyFiveMinutes();
Schedule::job(new CheckProviderWalletsJob)->everyFifteenMinutes();
Schedule::job(new RemindOpenSettlementsJob)->dailyAt('08:00');
Schedule::job(new ExpirePendingApprovalsJob)->hourly();
Schedule::job(new RetryFailedNotificationsJob)->everyThirtyMinutes();
Schedule::job(new CleanupMonitoringDataJob)->dailyAt('02:30');
Schedule::command('backup:database --keep=14')->dailyAt('01:15');
