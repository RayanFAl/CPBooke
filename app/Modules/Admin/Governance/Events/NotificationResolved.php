<?php

namespace App\Modules\Admin\Governance\Events;

final readonly class NotificationResolved
{
    public function __construct(
        public int $notificationLogId,
        public ?int $userId,
        public string $channel,
        public string $templateCode,
        public string $status,
        public int $retryCount,
        public bool $delivered,
        public ?string $failureReason,
        public ?string $sentAt,
        public ?string $failedAt,
        public string $occurredAt,
    ) {
    }
}