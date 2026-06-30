<?php

namespace App\Modules\Admin\Governance\Events;

final readonly class TransactionReconciled
{
    /**
     * @param  array<string, int>  $summary
     */
    public function __construct(
        public ?int $actorId,
        public string $actorType,
        public bool $repairMissingLedger,
        public array $summary,
        public int $criticalAnomaliesCount,
        public string $occurredAt,
    ) {
    }
}