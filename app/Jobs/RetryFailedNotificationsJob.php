<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Modules\Monitoring\Services\ApplicationEventRecorder;
use App\Modules\Notifications\Jobs\SendNotificationChannelJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RetryFailedNotificationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(ApplicationEventRecorder $recorder): void
    {
        $failed = NotificationLog::query()
            ->where('status', NotificationLog::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDay())
            ->where('retry_count', '<', 3)
            ->orderBy('id')
            ->limit(50)
            ->get();

        $retried = 0;

        foreach ($failed as $log) {
            try {
                $log->forceFill([
                    'retry_count' => ((int) $log->retry_count) + 1,
                    'status' => NotificationLog::STATUS_PENDING,
                    'failed_at' => null,
                ])->save();

                SendNotificationChannelJob::dispatch($log->id)
                    ->onQueue('notifications-'.$log->channel);

                $retried++;
            } catch (Throwable) {
                // continue
            }
        }

        if ($retried > 0) {
            $recorder->record(
                'system',
                'info',
                "Queued {$retried} failed notification(s) for retry",
                'notification_retry',
                ['count' => $retried],
            );
        }
    }
}
