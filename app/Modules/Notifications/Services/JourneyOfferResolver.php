<?php

namespace App\Modules\Notifications\Services;

use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\User;
use App\Models\UserLoyaltyProfile;
use App\Modules\Notifications\Support\NotificationTopics;
use App\Modules\Notifications\Support\OrderNotificationContext;
use Illuminate\Support\Collection;

class JourneyOfferResolver
{
    public const STAGE_AFTER_BOOKING = 'after_booking';

    public const STAGE_BEFORE_DEPARTURE = 'before_departure';

    public const STAGE_DURING_JOURNEY = 'during_journey';

    public const STAGE_AFTER_JOURNEY = 'after_journey';

    /**
     * Next Best Offer for this traveler at this journey stage.
     *
     * @return array{
     *     stage: string,
     *     checklist: list<array{code: string, ready: bool, label: string}>,
     *     missing_labels: string,
     *     next_best_offer: array<string, mixed>|null,
     *     offers: list<array<string, mixed>>
     * }
     */
    public function recommend(Order $flight, User $customer, string $stage): array
    {
        $catalog = $this->eligibleOffers($flight, $customer, $stage);
        $checklist = $this->checklist($flight, $customer);
        $missing = array_values(array_filter($checklist, static fn (array $item): bool => ! $item['ready']));

        return [
            'stage' => $stage,
            'checklist' => $checklist,
            'missing_labels' => implode(', ', array_column($missing, 'label')),
            'missing_labels_ar' => implode('، ', array_column($missing, 'label_ar')),
            'next_best_offer' => $catalog[0] ?? null,
            'offers' => $catalog,
        ];
    }

    /**
     * @return list<array{code: string, title: string, body: string, deep_link: string}>
     */
    public function preDepartureOffers(Order $flight, User $customer): array
    {
        return $this->recommend($flight, $customer, self::STAGE_BEFORE_DEPARTURE)['offers'];
    }

    /**
     * @return list<array{code: string, title: string, body: string, deep_link: string}>
     */
    public function arrivalOffers(Order $flight, User $customer): array
    {
        return $this->recommend($flight, $customer, self::STAGE_DURING_JOURNEY)['offers'];
    }

    /**
     * @return list<array{code: string, ready: bool, label: string}>
     */
    public function checklist(Order $flight, User $customer): array
    {
        $country = OrderNotificationContext::destinationCountry($flight);

        return [
            ['code' => 'flight', 'ready' => true, 'label' => 'Flight', 'label_ar' => 'الطيران'],
            ['code' => 'insurance', 'ready' => $this->hasInsuranceForTrip($customer, $flight), 'label' => 'Insurance', 'label_ar' => 'التأمين'],
            ['code' => 'esim', 'ready' => $country !== null && $this->hasActiveEsimForCountry($customer, $country), 'label' => 'eSIM', 'label_ar' => 'eSIM'],
            ['code' => 'hotel', 'ready' => $this->hasHotelAtDestination($customer, $flight), 'label' => 'Hotel', 'label_ar' => 'الفندق'],
            ['code' => 'car', 'ready' => $this->hasCarAtDestination($customer, $flight), 'label' => 'Car', 'label_ar' => 'السيارة'],
        ];
    }

    /**
     * @return list<array{code: string, title: string, body: string, deep_link: string, reason: string}>
     */
    private function eligibleOffers(Order $flight, User $customer, string $stage): array
    {
        $destination = OrderNotificationContext::destinationLabel($flight);
        $country = OrderNotificationContext::destinationCountry($flight);
        $city = OrderNotificationContext::destinationCitySlug($flight);
        $priority = match ($stage) {
            self::STAGE_AFTER_BOOKING => ['OFFER_INSURANCE', 'OFFER_ESIM', 'OFFER_HOTELS_AT_DESTINATION', 'OFFER_RETURN_FLIGHT'],
            self::STAGE_BEFORE_DEPARTURE => ['OFFER_ESIM', 'OFFER_INSURANCE', 'OFFER_HOTELS_AT_DESTINATION'],
            self::STAGE_DURING_JOURNEY => ['OFFER_HOTELS_AT_DESTINATION', 'OFFER_CARS_AT_DESTINATION', 'OFFER_ESIM'],
            self::STAGE_AFTER_JOURNEY => ['POST_TRIP_NEXT', 'LOYALTY_NEAR_REWARD'],
            default => ['OFFER_INSURANCE', 'OFFER_ESIM'],
        };

        $catalog = [];

        foreach ($priority as $code) {
            $offer = match ($code) {
                'OFFER_INSURANCE' => $this->insuranceOffer($flight, $customer, $destination),
                'OFFER_ESIM' => $this->esimOffer($flight, $customer, $destination, $country),
                'OFFER_HOTELS_AT_DESTINATION' => $this->hotelOffer($flight, $customer, $destination, $city),
                'OFFER_CARS_AT_DESTINATION' => $this->carOffer($flight, $customer, $destination, $city),
                'OFFER_RETURN_FLIGHT' => $this->returnOffer($flight, $destination),
                'POST_TRIP_NEXT' => $this->nextTripOffer($destination, $city),
                'LOYALTY_NEAR_REWARD' => $this->loyaltyOffer($customer),
                default => null,
            };

            if ($offer !== null) {
                $catalog[] = $offer;
            }
        }

        return $catalog;
    }

    /**
     * @return array<string, string>|null
     */
    private function insuranceOffer(Order $flight, User $customer, string $destination): ?array
    {
        if (! $this->offerEnabled('OFFER_INSURANCE')
            || ! $this->preferenceResolver()->topicEnabled($customer, NotificationTopics::INSURANCE)
            || $this->hasInsuranceForTrip($customer, $flight)
        ) {
            return null;
        }

        return [
            'code' => 'OFFER_INSURANCE',
            'title' => 'Protect your trip to '.$destination,
            'body' => 'One more step to cover your journey.',
            'deep_link' => '/insurance?order_id='.$flight->id,
            'reason' => 'missing_insurance',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function esimOffer(Order $flight, User $customer, string $destination, ?string $country): ?array
    {
        if (! $this->offerEnabled('OFFER_ESIM') || $country === null || $this->hasActiveEsimForCountry($customer, $country)) {
            return null;
        }

        return [
            'code' => 'OFFER_ESIM',
            'title' => 'Need internet in '.$destination.'?',
            'body' => 'Activate an eSIM and stay connected from the moment you land.',
            'deep_link' => '/esim?country='.$country,
            'reason' => 'missing_esim',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function hotelOffer(Order $flight, User $customer, string $destination, string $city): ?array
    {
        if (! $this->offerEnabled('OFFER_HOTELS_AT_DESTINATION')
            || ! $this->preferenceResolver()->topicEnabled($customer, NotificationTopics::HOTEL)
            || $this->hasHotelAtDestination($customer, $flight)
        ) {
            return null;
        }

        return [
            'code' => 'OFFER_HOTELS_AT_DESTINATION',
            'title' => 'Need a hotel in '.$destination.'?',
            'body' => 'Discover nearby stays and book from Booke.',
            'deep_link' => '/hotels?city='.rawurlencode($city),
            'reason' => 'missing_hotel',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function carOffer(Order $flight, User $customer, string $destination, string $city): ?array
    {
        if (! $this->offerEnabled('OFFER_CARS_AT_DESTINATION') || $this->hasCarAtDestination($customer, $flight)) {
            return null;
        }

        return [
            'code' => 'OFFER_CARS_AT_DESTINATION',
            'title' => 'Moving around '.$destination.'?',
            'body' => 'Rent a car from Booke for your stay.',
            'deep_link' => '/cars?city='.rawurlencode($city),
            'reason' => 'missing_car',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function returnOffer(Order $flight, string $destination): ?array
    {
        if (! $this->offerEnabled('OFFER_RETURN_FLIGHT') || ! $this->isOneWay($flight)) {
            return null;
        }

        $origin = OrderNotificationContext::originLabel($flight);

        return [
            'code' => 'OFFER_RETURN_FLIGHT',
            'title' => 'You Booked the outbound. What about the return?',
            'body' => 'Find a return from '.$destination.' to '.$origin.'.',
            'deep_link' => '/flights?origin='.rawurlencode(OrderNotificationContext::destinationCitySlug($flight)).'&destination='.rawurlencode(strtolower($origin)),
            'reason' => 'one_way_booking',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function nextTripOffer(string $destination, string $city): ?array
    {
        if (! $this->offerEnabled('POST_TRIP_NEXT')) {
            return null;
        }

        return [
            'code' => 'POST_TRIP_NEXT',
            'title' => 'How was '.$destination.'?',
            'body' => 'Ready for your next trip? Discover Booke deals from '.$destination.'.',
            'deep_link' => '/flights?origin='.rawurlencode($city),
            'reason' => 'post_trip',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function loyaltyOffer(User $customer): ?array
    {
        $profile = UserLoyaltyProfile::query()->where('user_id', $customer->id)->first();

        if ($profile === null || (int) $profile->progress_percentage < 80 || $profile->next_tier_id === null) {
            return null;
        }

        return [
            'code' => 'LOYALTY_NEAR_REWARD',
            'title' => 'You are close to your next reward',
            'body' => 'You are at '.$profile->progress_percentage.'% toward the next Booke tier.',
            'deep_link' => '/loyalty',
            'reason' => 'loyalty_progress',
        ];
    }

    public function hasActiveEsimForCountry(User $customer, string $country): bool
    {
        return $this->addonOrders($customer, Order::SERVICE_TYPE_ESIM)
            ->contains(function (Order $order) use ($country): bool {
                $orderCountry = OrderNotificationContext::destinationCountry($order);

                if ($orderCountry === $country) {
                    return true;
                }

                $haystack = strtoupper(OrderNotificationContext::destinationLabel($order).' '.json_encode($order->details ?? []));

                return str_contains($haystack, $country)
                    || ($country === 'TN' && (str_contains($haystack, 'TUNIS') || str_contains($haystack, 'تونس')));
            });
    }

    public function hasInsuranceForTrip(User $customer, Order $flight): bool
    {
        return $this->addonOrders($customer, Order::SERVICE_TYPE_INSURANCE)
            ->contains(function (Order $order) use ($flight): bool {
                $details = is_array($order->details) ? $order->details : [];
                $linkedId = $details['flight_order_id'] ?? $details['related_order_id'] ?? $details['order_id'] ?? null;

                if ((int) $linkedId === (int) $flight->id) {
                    return true;
                }

                $flightRef = (string) ($flight->booking_reference ?: '');
                $linkedRef = (string) ($details['flight_reference'] ?? $details['booking_reference'] ?? '');

                if ($flightRef !== '' && $linkedRef !== '' && strcasecmp($flightRef, $linkedRef) === 0) {
                    return true;
                }

                $flightCountry = OrderNotificationContext::destinationCountry($flight);
                $orderCountry = OrderNotificationContext::destinationCountry($order);

                return $flightCountry !== null && $orderCountry === $flightCountry;
            });
    }

    public function hasHotelAtDestination(User $customer, Order $flight): bool
    {
        $arrival = OrderNotificationContext::arrivalTime($flight)?->toDateString()
            ?? OrderNotificationContext::departureTime($flight)?->toDateString();
        $flightCity = OrderNotificationContext::destinationCitySlug($flight);
        $flightCountry = OrderNotificationContext::destinationCountry($flight);

        return $this->addonOrders($customer, Order::SERVICE_TYPE_HOTEL)
            ->contains(function (Order $hotel) use ($arrival, $flightCity, $flightCountry): bool {
                $checkIn = OrderNotificationContext::checkInDate($hotel)?->toDateString();

                if ($arrival !== null && $checkIn !== null && $checkIn !== $arrival) {
                    return false;
                }

                $hotelCity = OrderNotificationContext::destinationCitySlug($hotel);
                $hotelCountry = OrderNotificationContext::destinationCountry($hotel);

                if ($flightCity !== '' && $hotelCity !== '' && $flightCity === $hotelCity) {
                    return true;
                }

                return $flightCountry !== null && $hotelCountry === $flightCountry;
            });
    }

    public function hasCarAtDestination(User $customer, Order $flight): bool
    {
        $flightCity = OrderNotificationContext::destinationCitySlug($flight);

        return Order::query()
            ->where('customer_id', $customer->id)
            ->whereIn('service_type', ['car', 'car_rental', 'transfer'])
            ->whereIn('status', [
                Order::STATUS_PAID,
                Order::STATUS_PROCESSING,
                Order::STATUS_CONFIRMED,
                Order::STATUS_COMPLETED,
            ])
            ->where('created_at', '>=', now()->subDays(45))
            ->get()
            ->contains(function (Order $order) use ($flightCity): bool {
                $city = OrderNotificationContext::destinationCitySlug($order);

                return $flightCity === '' || $city === '' || $city === $flightCity;
            });
    }

    public function isOneWay(Order $flight): bool
    {
        $details = is_array($flight->details) ? $flight->details : [];

        if (! empty($details['return_date']) || ! empty($details['inbound']) || ! empty($details['return_time'])) {
            return false;
        }

        $tripType = strtolower((string) ($details['trip_type'] ?? $details['type'] ?? 'one_way'));

        return in_array($tripType, ['one_way', 'oneway', 'ow', ''], true);
    }

    private function offerEnabled(string $code): bool
    {
        $template = NotificationTemplate::query()->where('code', $code)->first();

        return $template === null || (bool) $template->is_active;
    }

    /**
     * @return Collection<int, Order>
     */
    private function addonOrders(User $customer, string $serviceType)
    {
        return Order::query()
            ->where('customer_id', $customer->id)
            ->where('service_type', $serviceType)
            ->whereIn('status', [
                Order::STATUS_PAID,
                Order::STATUS_PROCESSING,
                Order::STATUS_CONFIRMED,
                Order::STATUS_TICKETED,
                Order::STATUS_COMPLETED,
            ])
            ->where('created_at', '>=', now()->subDays(45))
            ->get();
    }

    private function preferenceResolver(): NotificationPreferenceResolver
    {
        return app(NotificationPreferenceResolver::class);
    }
}
