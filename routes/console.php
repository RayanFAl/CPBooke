<?php

use App\Jobs\CheckProviderWalletsJob;
use App\Jobs\CleanupMonitoringDataJob;
use App\Jobs\ExpirePendingApprovalsJob;
use App\Jobs\ProcessScheduledAccountDeletionsJob;
use App\Jobs\RemindOpenSettlementsJob;
use App\Jobs\RetryFailedNotificationsJob;
use App\Jobs\RunSystemHealthProbesJob;
use App\Jobs\SendBookingReminderNotificationsJob;
use App\Jobs\SendDailyFxSalesReportJob;
use App\Modules\Notifications\Services\TravelMarketingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RunSystemHealthProbesJob)->everyFiveMinutes();
Schedule::job(new CheckProviderWalletsJob)->everyFifteenMinutes();
Schedule::job(new RemindOpenSettlementsJob)->dailyAt('08:00');
Schedule::job(new SendDailyFxSalesReportJob)->dailyAt('07:00');
Schedule::job(new ExpirePendingApprovalsJob)->hourly();
Schedule::job(new RetryFailedNotificationsJob)->everyThirtyMinutes();
Schedule::job(new SendBookingReminderNotificationsJob)->everyFifteenMinutes();
Schedule::call(function (): void {
    app(TravelMarketingService::class)->dispatchDue(now());
})->everyMinute()->name('travel-marketing-seat-alerts');
Schedule::job(new CleanupMonitoringDataJob)->dailyAt('02:30');
Schedule::job(new ProcessScheduledAccountDeletionsJob)->dailyAt('03:30');
Schedule::command('backup:database --keep=14')->dailyAt('01:15');
Schedule::command('saved-passengers:purge-passport-images')->dailyAt('03:10');
