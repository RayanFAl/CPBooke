<?php

namespace App\Modules\Api\DTO;

final readonly class CreateOrderDTO
{
    /**
     * @param  array<string, mixed>  $requestPayload
     */
    public function __construct(
        public string $providerName,
        public string $currency,
        public string $totalAmount,
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
            currency: strtoupper($data['currency']),
            totalAmount: number_format((float) $data['total_amount'], 2, '.', ''),
            requestPayload: $data['request_payload'] ?? [],
        );
    }
}