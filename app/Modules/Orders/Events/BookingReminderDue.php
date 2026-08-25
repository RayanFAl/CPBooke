<?php

namespace App\Modules\Orders\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingReminderDue
{
    use Dispatchable;
    use SerializesModels;

    public const WINDOW_24H = '24h';

    public const WINDOW_3H = '3h';

    public const WINDOW_1H = '1h';

    public const WINDOW_HOTEL_CHECKIN_24H = 'hotel_checkin_24h';

    public const WINDOW_ARRIVAL = 'destination_arrival';

    public const WINDOW_OFFER_ESIM = 'offer_esim';

    public const WINDOW_OFFER_INSURANCE = 'offer_insurance';

    public const WINDOW_OFFER_HOTELS = 'offer_hotels';

    public const WINDOW_OFFER_CARS = 'offer_cars';

    public const WINDOW_PAYMENT_NUDGE = 'payment_nudge';

    public const WINDOW_PAYMENT_EXPIRED = 'payment_expired';

    public const WINDOW_POST_TRIP = 'post_trip';

    public const WINDOW_HOTEL_CANCEL_DEADLINE = 'hotel_cancel_deadline';

    public const WINDOW_CHECKIN_OPEN = 'checkin_open';

    public const WINDOW_HOTEL_CHECKOUT = 'hotel_checkout';

    public const WINDOW_ESIM_ACTIVATION = 'esim_activation';

    public function __construct(
        public readonly Order $order,
        public readonly string $window,
    ) {}
}
