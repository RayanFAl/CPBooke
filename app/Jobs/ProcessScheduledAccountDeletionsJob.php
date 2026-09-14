<?php

namespace App\Jobs;

use App\Modules\Api\User\Services\CustomerAccountDeletionService;
use App\Modules\Monitoring\Services\ApplicationEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessScheduledAccountDeletionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        CustomerAccountDeletionService $deletionService,
        ApplicationEventRecorder $recorder,
    ): void {
        $processed = $deletionService->processDueDeletions();

        if ($processed > 0) {
            $recorder->record(
                'system',
                'info',
                'Processed '.$processed.' scheduled customer account deletion(s)',
                'scheduler',
                ['count' => $processed],
            );
        }
    }
}
