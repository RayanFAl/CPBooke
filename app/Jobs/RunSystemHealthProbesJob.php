<?php

namespace App\Jobs;

use App\Modules\Monitoring\Services\SystemHealthProbeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunSystemHealthProbesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function handle(SystemHealthProbeService $probeService): void
    {
        $probeService->runAndStore();
    }
}
