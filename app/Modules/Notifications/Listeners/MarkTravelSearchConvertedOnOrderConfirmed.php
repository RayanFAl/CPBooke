<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Services\TravelMarketingService;
use App\Modules\Orders\Events\OrderConfirmed;

class MarkTravelSearchConvertedOnOrderConfirmed
{
    public function __construct(
        private readonly TravelMarketingService $travelMarketingService,
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $customer = $event->order->customer ?? $event->order->customer()->first();

        if ($customer === null) {
            return;
        }

        $this->travelMarketingService->markConvertedForCustomer($customer);
    }
}
