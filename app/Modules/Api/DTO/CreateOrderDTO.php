<?php

namespace App\Modules\Api\DTO;

use App\Models\Order;

final readonly class CreateOrderDTO
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $requestPayload
     */
    public function __construct(
        public string $providerName,
        public string $currency,
        public string $totalAmount,
        public string $serviceType,
        public array $details,
        public array $requestPayload,
    ) {
    }

    /**
     * Create a DTO from validated request data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            providerName: $data['provider_name'],
            currency: Order::DEFAULT_CURRENCY,
            totalAmount: number_format((float) $data['total_amount'], 2, '.', ''),
            serviceType: $data['service_type'],
            details: $data['details'] ?? [],
            requestPayload: $data['details'] ?? [],
        );
    }
}