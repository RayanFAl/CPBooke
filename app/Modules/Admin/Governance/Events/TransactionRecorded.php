<?php

namespace App\Modules\Admin\Governance\Events;

final readonly class TransactionRecorded
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $transactionId,
        public ?int $orderId,
        public string $type,
        public string $status,
        public string $amount,
        public string $currency,
        public ?string $source,
        public int|string|null $sourceId,
        public ?string $referenceType,
        public int|string|null $referenceId,
        public ?string $debitAccount,
        public ?string $creditAccount,
        public ?string $performedByType,
        public int|string|null $performedById,
        public array $metadata,
        public string $occurredAt,
    ) {
    }
}