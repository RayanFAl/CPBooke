<?php

namespace App\Modules\Admin\Governance\Events;

final readonly class RbacAuditRecorded
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int $auditLogId,
        public ?int $actorId,
        public ?string $permission,
        public string $action,
        public ?string $targetType,
        public int|string|null $targetId,
        public array $context,
        public ?string $ipAddress,
        public ?string $userAgent,
        public string $occurredAt,
    ) {
    }
}