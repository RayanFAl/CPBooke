<?php

namespace App\Support\Airports;

use App\Models\AirportStat;
use App\Models\Order;
use App\Modules\Notifications\Support\OrderNotificationContext;
use Illuminate\Support\Facades\Schema;

class AirportPopularityService
{
    public function recordFlightSearch(?string $origin, ?string $destination): void
    {
        foreach ([$origin, $destination] as $place) {
            $this->incrementSearch($place);
        }
    }

    public function recordExactAirportQuery(string $search): void
    {
        $this->incrementSearch($search);
    }

    public function recordTravelFromOrder(Order $order): void
    {
        if ($order->service_type !== Order::SERVICE_TYPE_FLIGHT) {
            return;
        }

        foreach ($this->airportCodesFromOrder($order) as $code) {
            $this->incrementTravel($code);
        }
    }

    public function incrementSearch(?string $place): void
    {
        $key = $this->keyFromPlace($place);

        if ($key === null || ! Schema::hasTable('airport_stats')) {
            return;
        }

        $stat = AirportStat::query()->firstOrCreate(
            ['airport_key' => $key],
            ['search_count' => 0, 'travel_count' => 0],
        );

        $stat->increment('search_count');
        $stat->forceFill(['last_searched_at' => now()])->save();
    }

    public function incrementTravel(?string $place): void
    {
        $key = $this->keyFromPlace($place);

        if ($key === null || ! Schema::hasTable('airport_stats')) {
            return;
        }

        $stat = AirportStat::query()->firstOrCreate(
            ['airport_key' => $key],
            ['search_count' => 0, 'travel_count' => 0],
        );

        $stat->increment('travel_count');
        $stat->forceFill(['last_traveled_at' => now()])->save();
    }

    public function keyFromPlace(?string $place): ?string
    {
        if (! is_string($place)) {
            return null;
        }

        $code = strtoupper(trim($place));

        if (preg_match('/^[A-Z]{3}$/', $code) === 1) {
            return "IATA:{$code}";
        }

        if (preg_match('/^[A-Z]{4}$/', $code) === 1) {
            return "ICAO:{$code}";
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function airportCodesFromOrder(Order $order): array
    {
        $details = is_array($order->details) ? $order->details : [];
        $segments = is_array($details['segments'] ?? null) ? $details['segments'] : [];
        $codes = [
            OrderNotificationContext::originAirport($order),
            OrderNotificationContext::destinationAirportCode($order),
        ];

        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $codes[] = is_string($segment['departure_airport'] ?? null) ? $segment['departure_airport'] : null;
            $codes[] = is_string($segment['arrival_airport'] ?? null) ? $segment['arrival_airport'] : null;
        }

        return array_values(array_unique(array_filter(
            array_map(fn (?string $code): string => strtoupper(trim((string) $code)), $codes),
            fn (string $code): bool => $code !== '',
        )));
    }
}
