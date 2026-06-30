<?php

namespace App\Modules\Pricing\DTO;

final readonly class BasePriceQuote
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $serviceType,
        public string $currency,
        public string $baseAmount,
        public array $metadata = [],
    ) {
    }
}