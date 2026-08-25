<?php

namespace App\Modules\Orders\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlightStatusUpdated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public readonly Order $order,
        public readonly array $changes = [],
        public readonly ?string $summary = null,
    ) {
    }
}
