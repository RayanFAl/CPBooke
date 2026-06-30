<?php

namespace App\Modules\Notifications\Jobs;

use App\Models\NotificationLog;
use App\Modules\Admin\Governance\Events\NotificationResolved;
use App\Modules\Admin\Governance\Services\GovernanceEventDispatcher;
use App\Modules\Notifications\Services\NotificationChannelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendNotificationChannelJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $notificationLogId,
    ) {
    }

    /**
     * Determine the exponential backoff intervals.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(NotificationChannelManager $channelManager, GovernanceEventDispatcher $governanceEventDispatcher): void
    {
        $log = NotificationLog::query()
            ->with(['template', 'user'])
            ->findOrFail($this->notificationLogId);

        if ($log->status === NotificationLog::STATUS_SENT) {
            return;
        }

        $log->forceFill([
            'retry_count' => max($log->retry_count, $this->attempts() - 1),
            'status' => NotificationLog::STATUS_PENDING,
        ])->save();

        $result = $channelManager
            ->driver($log->channel)
            ->send($log, $log->template, $log->user, $log->variables ?? []);

        $delivered = (bool) ($result['delivered'] ?? true);

        $log->forceFill([
            'status' => $delivered ? NotificationLog::STATUS_SENT : NotificationLog::STATUS_FAILED,
            'response_payload' => $result,
            'sent_at' => $delivered ? now() : null,
            'failed_at' => $delivered ? null : now(),
        ])->save();

        $governanceEventDispatcher->dispatch($this->resolvedEvent($log, $delivered));
    }

    public function failed(Throwable $exception): void
    {
        $log = NotificationLog::query()->find($this->notificationLogId);

        if ($log === null) {
            return;
        }

        $payload = $log->response_payload ?? [];
        $payload['error'] = $exception->getMessage();

        $log->forceFill([
            'status' => NotificationLog::STATUS_FAILED,
            'retry_count' => max($log->retry_count, $this->attempts() - 1),
            'response_payload' => $payload,
            'failed_at' => now(),
        ])->save();

        app(GovernanceEventDispatcher::class)->dispatch($this->resolvedEvent($log, false));
    }

    private function resolvedEvent(NotificationLog $log, bool $delivered): NotificationResolved
    {
        return new NotificationResolved(
            notificationLogId: $log->id,
            userId: $log->user_id,
            channel: $log->channel,
            templateCode: $log->template_code,
            status: $log->status,
            retryCount: $log->retry_count,
            delivered: $delivered,
            failureReason: $log->response_payload['error'] ?? $log->response_payload['reason'] ?? null,
            sentAt: $log->sent_at?->toIso8601String(),
            failedAt: $log->failed_at?->toIso8601String(),
            occurredAt: now()->toIso8601String(),
        );
    }
}