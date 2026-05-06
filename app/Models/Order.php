<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'provider_name',
    'external_booking_id',
    'booking_reference',
    'status',
    'currency',
    'total_amount',
    'request_payload',
    'response_payload',
    'error_message',
])]
class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the customer who owns the order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the supported statuses.
     *
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get the statuses that operations and support may apply manually.
     *
     * @return array<int, string>
     */
    public static function adminUpdatableStatuses(): array
    {
        return [
            self::STATUS_CONFIRMED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
    }
}