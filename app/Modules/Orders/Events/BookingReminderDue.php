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

    public const WINDOW_HOTEL_CHECKIN_24H = 'hotel_checkin_24h';

    public function __construct(
        public readonly Order $order,
        public readonly string $window,
    ) {
    }
}
