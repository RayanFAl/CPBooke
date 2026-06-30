<?php

namespace App\Modules\Admin\Governance\Events;

final readonly class AccessDenied
{
    /**
     * @param  array<int, string>  $permissions
     * @param  array<int, string>  $roles
     */
    public function __construct(
        public ?int $actorId,
        public string $actorType,
        public string $guard,
        public array $permissions,
        public array $roles,
        public string $reason,
        public ?string $routeName,
        public ?string $method,
        public ?string $path,
        public ?string $ipAddress,
        public string $occurredAt,
    ) {
    }
}