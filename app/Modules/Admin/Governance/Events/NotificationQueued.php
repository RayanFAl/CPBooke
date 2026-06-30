<?php

namespace App\Modules\Admin\Governance\Events;

final readonly class NotificationQueued
{
    public function __construct(
        public int $notificationLogId,
        public ?int $userId,
        public string $channel,
        public string $templateCode,
        public ?int $templateVersion,
        public ?string $eventClass,
        public ?string $notificationType,
        public ?string $relatedType,
        public int|string|null $relatedId,
        public string $status,
        public string $queue,
        public string $occurredAt,
    ) {
    }
}