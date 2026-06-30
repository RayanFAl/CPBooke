<?php

namespace App\Modules\Admin\Governance\Events;

final readonly class FinanceAnomalyDetected
{
    /**
     * @param  array<string, mixed>|null  $order
     * @param  array<string, mixed>|null  $transaction
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $code,
        public string $severity,
        public string $message,
        public ?array $order,
        public ?array $transaction,
        public array $context,
        public string $detectedAt,
        public string $occurredAt,
    ) {
    }
}