<?php

namespace App\Modules\Admin\Governance\Events;

final readonly class LoyaltyStateChanged
{
    /**
     * @param  array<string, mixed>  $metricsSnapshot
     * @param  array<string, mixed>|null  $ruleSnapshot
     * @param  array<int, string>  $benefitCodes
     */
    public function __construct(
        public int $historyId,
        public int $userId,
        public ?int $fromTierId,
        public ?string $fromTierName,
        public ?int $toTierId,
        public ?string $toTierName,
        public string $action,
        public ?int $orderId,
        public ?string $triggerEventClass,
        public array $metricsSnapshot,
        public ?array $ruleSnapshot,
        public array $benefitCodes,
        public string $changedAt,
        public string $occurredAt,
    ) {
    }
}