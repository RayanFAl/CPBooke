<?php

namespace App\Modules\Notifications\Services;

use App\Models\Order;
use App\Models\PriceAlert;
use App\Models\SeatAlert;
use App\Models\TravelSearchIntent;
use App\Models\User;
use App\Modules\Notifications\Events\AbandonedFlightSearchDue;
use App\Modules\Notifications\Events\PriceAlertHit;
use App\Modules\Notifications\Events\SeatAlertAvailable;
use App\Modules\Notifications\Support\OrderNotificationContext;
use App\Support\Airports\AirportPopularityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class TravelMarketingService
{
    public function __construct(
        private readonly AirportPopularityService $airportPopularityService,
    ) {
    }

    /**
     * Upsert the traveler's latest flight search so Abandoned Search / Price Alerts / Seat Alerts can fire.
     *
     * @param  array{origin: string, destination: string, departure_date?: string|null, return_date?: string|null, flight_number?: string|null, offer_id?: string|null, cabin?: string|null, lowest_price?: float|int|string|null, available_seats?: int|string|null, currency?: string|null}  $input
     */
    public function recordSearch(User $user, array $input): TravelSearchIntent
    {
        $origin = $this->normalizePlace((string) $input['origin']);
        $destination = $this->normalizePlace((string) $input['destination']);
        $departureDate = $this->normalizeDate($input['departure_date'] ?? null);
        $returnDate = $this->normalizeDate($input['return_date'] ?? null);
        $routeKey = TravelSearchIntent::routeKeyFor($origin, $destination, $departureDate);
        $price = $this->normalizePrice($input['lowest_price'] ?? null);
        $seats = $this->normalizeSeats($input['available_seats'] ?? null);
        $currency = strtoupper((string) ($input['currency'] ?? 'LYD')) ?: 'LYD';
        $flightNumber = $this->normalizeFlightNumber($input['flight_number'] ?? null);
        $offerId = $this->normalizeOptionalString($input['offer_id'] ?? null, 120);
        $cabin = $this->normalizeCabin($input['cabin'] ?? null);

        $intent = TravelSearchIntent::query()->firstOrNew([
            'user_id' => $user->id,
            'route_key' => $routeKey,
        ]);

        $previousPrice = $intent->exists ? $intent->last_seen_price : null;

        $intent->fill([
            'origin' => $origin,
            'destination' => $destination,
            'departure_date' => $departureDate,
            'return_date' => $returnDate,
            'currency' => $currency,
            'last_searched_at' => now(),
        ]);

        if ($flightNumber !== null) {
            $intent->flight_number = $flightNumber;
        }

        if ($offerId !== null) {
            $intent->offer_id = $offerId;
        }

        if ($cabin !== null) {
            $intent->cabin = $cabin;
        }

        if (Schema::hasColumn('travel_search_intents', 'search_count')) {
            $intent->search_count = (int) ($intent->search_count ?: 0) + 1;
        }

        if ($price !== null) {
            $intent->previous_seen_price = $previousPrice;
            $intent->last_seen_price = $price;
            if (Schema::hasColumn('travel_search_intents', 'results_viewed_at')) {
                $intent->results_viewed_at = $intent->results_viewed_at ?: now();
            }
        }

        if ($seats !== null) {
            $intent->last_seen_seats = $seats;
            if (Schema::hasColumn('travel_search_intents', 'results_viewed_at')) {
                $intent->results_viewed_at = $intent->results_viewed_at ?: now();
            }
        }

        if ($this->userBookedRoute($user, $origin, $destination, $departureDate)) {
            $intent->converted_at = $intent->converted_at ?: now();
        }

        $intent->save();

        $this->airportPopularityService->recordFlightSearch($origin, $destination);

        return $intent->fresh() ?? $intent;
    }

    /**
     * @param  array{origin: string, destination: string, departure_date?: string|null, target_price: float|int|string, currency?: string|null}  $input
     */
    public function upsertPriceAlert(User $user, array $input): PriceAlert
    {
        $origin = $this->normalizePlace((string) $input['origin']);
        $destination = $this->normalizePlace((string) $input['destination']);
        $departureDate = $this->normalizeDate($input['departure_date'] ?? null);
        $routeKey = TravelSearchIntent::routeKeyFor($origin, $destination, $departureDate);
        $target = $this->normalizePrice($input['target_price']) ?? 0.0;
        $currency = strtoupper((string) ($input['currency'] ?? 'LYD')) ?: 'LYD';

        $alert = PriceAlert::query()->firstOrNew([
            'user_id' => $user->id,
            'route_key' => $routeKey,
            'target_price' => $target,
        ]);

        $alert->fill([
            'origin' => $origin,
            'destination' => $destination,
            'departure_date' => $departureDate,
            'currency' => $currency,
            'is_active' => true,
        ]);
        $alert->save();

        return $alert->fresh() ?? $alert;
    }

    /**
     * @param  array{origin: string, destination: string, departure_date?: string|null, flight_number?: string|null, offer_id?: string|null, cabin?: string|null, min_seats: int|string}  $input
     */
    public function upsertSeatAlert(User $user, array $input): SeatAlert
    {
        $origin = $this->normalizePlace((string) $input['origin']);
        $destination = $this->normalizePlace((string) $input['destination']);
        $departureDate = $this->normalizeDate($input['departure_date'] ?? null);
        $routeKey = TravelSearchIntent::routeKeyFor($origin, $destination, $departureDate);
        $flightNumber = $this->normalizeFlightNumber($input['flight_number'] ?? null);
        $offerId = $this->normalizeOptionalString($input['offer_id'] ?? null, 120);
        $cabin = $this->normalizeCabin($input['cabin'] ?? null);
        $minSeats = max(1, (int) ($input['min_seats'] ?? 1));
        $watchKey = SeatAlert::watchKeyFor($routeKey, $flightNumber, $offerId, $cabin);

        $alert = SeatAlert::query()->firstOrNew([
            'user_id' => $user->id,
            'watch_key' => $watchKey,
            'min_seats' => $minSeats,
        ]);

        $alert->fill([
            'origin' => $origin,
            'destination' => $destination,
            'route_key' => $routeKey,
            'departure_date' => $departureDate,
            'flight_number' => $flightNumber,
            'offer_id' => $offerId,
            'cabin' => $cabin,
            'is_active' => true,
        ]);
        $alert->save();

        return $alert->fresh() ?? $alert;
    }

    public function dispatchDue(Carbon $now): void
    {
        $this->markConvertedIntents();
        $this->dispatchAbandonedSearches($now);
        $this->dispatchPriceAlerts();
        $this->dispatchSeatAlerts();
    }

    private function dispatchAbandonedSearches(Carbon $now): void
    {
        TravelSearchIntent::query()
            ->with('user')
            ->whereNull('converted_at')
            ->whereBetween('last_searched_at', [
                $now->copy()->subHours(48),
                $now->copy()->subHours(2),
            ])
            ->where(function ($query): void {
                $query->whereNull('abandoned_notified_at')
                    ->orWhereColumn('abandoned_notified_at', '<', 'last_searched_at');
            })
            ->orderBy('id')
            ->chunkById(100, function ($intents): void {
                foreach ($intents as $intent) {
                    if ($intent->user === null) {
                        continue;
                    }

                    event(new AbandonedFlightSearchDue($intent));
                    $intent->forceFill(['abandoned_notified_at' => now()])->save();
                }
            });
    }

    private function dispatchPriceAlerts(): void
    {
        PriceAlert::query()
            ->with('user')
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(100, function ($alerts): void {
                foreach ($alerts as $alert) {
                    if ($alert->user === null) {
                        continue;
                    }

                    $intent = TravelSearchIntent::query()
                        ->where('user_id', $alert->user_id)
                        ->where('route_key', $alert->route_key)
                        ->first();

                    if ($intent === null || $intent->last_seen_price === null) {
                        continue;
                    }

                    $current = (float) $intent->last_seen_price;
                    $target = (float) $alert->target_price;

                    if ($current > $target) {
                        continue;
                    }

                    $lastTriggered = $alert->last_triggered_price !== null
                        ? (float) $alert->last_triggered_price
                        : null;

                    if ($lastTriggered !== null && $current >= $lastTriggered) {
                        continue;
                    }

                    event(new PriceAlertHit($alert, $current));
                    $alert->forceFill([
                        'last_triggered_at' => now(),
                        'last_triggered_price' => $current,
                    ])->save();
                }
            });
    }

    private function dispatchSeatAlerts(): void
    {
        SeatAlert::query()
            ->with('user')
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(100, function ($alerts): void {
                foreach ($alerts as $alert) {
                    if ($alert->user === null) {
                        continue;
                    }

                    $intent = TravelSearchIntent::query()
                        ->where('user_id', $alert->user_id)
                        ->where('route_key', $alert->route_key)
                        ->first();

                    if ($intent === null || $intent->last_seen_seats === null) {
                        continue;
                    }

                    if (! $this->seatAlertMatchesIntent($alert, $intent)) {
                        continue;
                    }

                    $current = (int) $intent->last_seen_seats;
                    $minSeats = (int) $alert->min_seats;

                    if ($current < $minSeats) {
                        continue;
                    }

                    $lastTriggered = $alert->last_triggered_seats !== null
                        ? (int) $alert->last_triggered_seats
                        : null;

                    // Re-fire only when more seats became available than last notification.
                    if ($lastTriggered !== null && $current <= $lastTriggered) {
                        continue;
                    }

                    event(new SeatAlertAvailable($alert, $current));
                    $alert->forceFill([
                        'last_triggered_at' => now(),
                        'last_triggered_seats' => $current,
                    ])->save();
                }
            });
    }

    private function seatAlertMatchesIntent(SeatAlert $alert, TravelSearchIntent $intent): bool
    {
        if ($alert->flight_number !== null && $alert->flight_number !== '') {
            if (strcasecmp((string) $intent->flight_number, $alert->flight_number) !== 0) {
                return false;
            }
        }

        if ($alert->offer_id !== null && $alert->offer_id !== '') {
            if ((string) $intent->offer_id !== $alert->offer_id) {
                return false;
            }
        }

        if ($alert->cabin !== null && $alert->cabin !== '') {
            if (strcasecmp((string) $intent->cabin, $alert->cabin) !== 0) {
                return false;
            }
        }

        return true;
    }

    public function markConvertedForCustomer(User $user): void
    {
        TravelSearchIntent::query()
            ->where('user_id', $user->id)
            ->whereNull('converted_at')
            ->orderBy('id')
            ->each(function (TravelSearchIntent $intent) use ($user): void {
                if ($this->userBookedRoute(
                    $user,
                    $intent->origin,
                    $intent->destination,
                    $intent->departure_date?->toDateString(),
                )) {
                    $intent->forceFill(['converted_at' => now()])->save();
                }
            });
    }

    private function markConvertedIntents(): void
    {
        TravelSearchIntent::query()
            ->with('user')
            ->whereNull('converted_at')
            ->orderBy('id')
            ->chunkById(100, function ($intents): void {
                foreach ($intents as $intent) {
                    if ($intent->user === null) {
                        continue;
                    }

                    $this->markConvertedForCustomer($intent->user);
                }
            });
    }

    public function userBookedRoute(User $user, string $origin, string $destination, ?string $departureDate): bool
    {
        $originKey = strtolower($origin);
        $destinationKey = strtolower($destination);

        return Order::query()
            ->where('customer_id', $user->id)
            ->where('service_type', Order::SERVICE_TYPE_FLIGHT)
            ->whereIn('status', [
                Order::STATUS_PAID,
                Order::STATUS_PROCESSING,
                Order::STATUS_CONFIRMED,
                Order::STATUS_TICKETED,
                Order::STATUS_COMPLETED,
            ])
            ->where('created_at', '>=', now()->subDays(45))
            ->get()
            ->contains(function (Order $order) use ($originKey, $destinationKey, $departureDate): bool {
                $orderOrigin = strtolower(OrderNotificationContext::originLabel($order));
                $orderDestination = strtolower(OrderNotificationContext::destinationLabel($order));
                $orderCity = OrderNotificationContext::destinationCitySlug($order);

                if (! $this->placeMatches($originKey, $orderOrigin)
                    || (! $this->placeMatches($destinationKey, $orderDestination) && ! $this->placeMatches($destinationKey, $orderCity))
                ) {
                    return false;
                }

                if ($departureDate === null) {
                    return true;
                }

                $orderDeparture = OrderNotificationContext::departureTime($order)?->toDateString();

                return $orderDeparture === null || $orderDeparture === $departureDate;
            });
    }

    private function placeMatches(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        return $left === $right
            || str_contains($left, $right)
            || str_contains($right, $left);
    }

    private function normalizePlace(string $value): string
    {
        $trimmed = trim($value);

        if (preg_match('/^[A-Za-z]{3}$/', $trimmed) === 1) {
            return strtoupper($trimmed);
        }

        return $trimmed;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function normalizeSeats(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    private function normalizeFlightNumber(mixed $value): ?string
    {
        $normalized = $this->normalizeOptionalString($value, 32);

        return $normalized !== null ? strtoupper($normalized) : null;
    }

    private function normalizeCabin(mixed $value): ?string
    {
        $normalized = $this->normalizeOptionalString($value, 32);

        return $normalized !== null ? strtolower($normalized) : null;
    }

    private function normalizeOptionalString(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $maxLength);
    }
}
