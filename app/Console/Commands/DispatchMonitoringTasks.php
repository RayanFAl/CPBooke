<?php

namespace App\Console\Commands;

use App\Jobs\CheckProviderWalletsJob;
use App\Jobs\CleanupMonitoringDataJob;
use App\Jobs\ExpirePendingApprovalsJob;
use App\Jobs\RemindOpenSettlementsJob;
use App\Jobs\RetryFailedNotificationsJob;
use App\Jobs\RunSystemHealthProbesJob;
use Illuminate\Console\Command;

class DispatchMonitoringTasks extends Command
{
    protected $signature = 'monitoring:dispatch {task? : health|wallets|settlements|approvals|notifications|cleanup|all}';

    protected $description = 'Dispatch monitoring / ops background jobs';

    public function handle(): int
    {
        $task = $this->argument('task') ?? 'all';

        $map = [
            'health' => RunSystemHealthProbesJob::class,
            'wallets' => CheckProviderWalletsJob::class,
            'settlements' => RemindOpenSettlementsJob::class,
            'approvals' => ExpirePendingApprovalsJob::class,
            'notifications' => RetryFailedNotificationsJob::class,
            'cleanup' => CleanupMonitoringDataJob::class,
        ];

        $selected = $task === 'all' ? array_keys($map) : [$task];

        foreach ($selected as $key) {
            if (! isset($map[$key])) {
                $this->error("Unknown task [{$key}]");

                return self::FAILURE;
            }

            $map[$key]::dispatch();
            $this->info("Dispatched {$key}");
        }

        return self::SUCCESS;
    }
}
