<?php

namespace App\Modules\Pricing\DTO;

use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;

final readonly class PricingContext
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public ?User $user,
        public string $serviceType,
        public string $currency,
        public string $baseAmount,
        public string $source,
        public array $attributes = [],
        public ?Order $order = null,
        public ?CarbonInterface $requestedAt = null,
    ) {
    }
}