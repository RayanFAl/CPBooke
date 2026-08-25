<?php

namespace App\Modules\Airports\Listeners;

use App\Modules\Orders\Events\OrderConfirmed;
use App\Support\Airports\AirportPopularityService;

class RecordAirportTravelOnOrderConfirmed
{
    public function __construct(
        private readonly AirportPopularityService $airportPopularityService,
    ) {
    }

    public function handle(OrderConfirmed $event): void
    {
        $this->airportPopularityService->recordTravelFromOrder($event->order);
    }
}
