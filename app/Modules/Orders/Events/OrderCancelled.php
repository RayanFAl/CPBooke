<?php

namespace App\Modules\Orders\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelled
{
    use Dispatchable;
    use SerializesModels;

    public const SOURCE_AIRLINE = 'airline';

    public const SOURCE_CUSTOMER = 'customer';

    public const SOURCE_ADMIN = 'admin';

    public function __construct(
        public readonly Order $order,
        public readonly string $source = self::SOURCE_CUSTOMER,
        public readonly ?string $reason = null,
    ) {}
}
