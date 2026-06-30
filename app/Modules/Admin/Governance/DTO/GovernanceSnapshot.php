<?php

namespace App\Modules\Admin\Governance\DTO;

class GovernanceSnapshot
{
    /**
     * @param  array<string, mixed>  $rbac
     * @param  array<string, mixed>  $finance
     * @param  array<string, mixed>  $notifications
     * @param  array<string, mixed>  $loyalty
     */
    public function __construct(
        public readonly array $rbac,
        public readonly array $finance,
        public readonly array $notifications,
        public readonly array $loyalty,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rbac' => $this->rbac,
            'finance' => $this->finance,
            'notifications' => $this->notifications,
            'loyalty' => $this->loyalty,
        ];
    }
}