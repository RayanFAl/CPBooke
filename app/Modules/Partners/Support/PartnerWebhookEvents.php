<?php

namespace App\Modules\Partners\Support;

final class PartnerWebhookEvents
{
    public const ORDER_CREATED = 'order.created';

    public const ORDER_CONFIRMED = 'order.confirmed';

    public const PAYMENT_SUCCEEDED = 'payment.succeeded';

    public const REFUND_ISSUED = 'refund.issued';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::ORDER_CREATED,
            self::ORDER_CONFIRMED,
            self::PAYMENT_SUCCEEDED,
            self::REFUND_ISSUED,
        ];
    }
}
