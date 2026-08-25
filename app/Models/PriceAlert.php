<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'origin',
    'destination',
    'route_key',
    'departure_date',
    'target_price',
    'last_triggered_price',
    'currency',
    'is_active',
    'last_triggered_at',
])]
class PriceAlert extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'target_price' => 'decimal:2',
            'last_triggered_price' => 'decimal:2',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function notificationPayload(?float $currentPrice = null): array
    {
        $price = $currentPrice ?? ($this->last_triggered_price !== null ? (float) $this->last_triggered_price : null);

        return [
            'user_name' => $this->user?->full_name ?: $this->user?->name ?: 'Customer',
            'origin' => $this->origin,
            'destination' => $this->destination,
            'route' => $this->origin.' → '.$this->destination,
            'departure_date' => $this->departure_date?->toFormattedDateString() ?: '',
            'price' => $price !== null ? number_format($price, 0, '.', ',') : '—',
            'target_price' => number_format((float) $this->target_price, 0, '.', ','),
            'currency' => $this->currency ?: 'LYD',
            'deep_link' => $this->deepLink(),
            'notification_type' => 'tag',
        ];
    }

    public function deepLink(): string
    {
        $query = http_build_query(array_filter([
            'origin' => $this->origin,
            'destination' => $this->destination,
            'date' => $this->departure_date?->toDateString(),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        return '/flights?'.$query;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPassengerArray(): array
    {
        return [
            'id' => (string) $this->id,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'departure_date' => $this->departure_date?->toDateString(),
            'target_price' => (float) $this->target_price,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'deep_link' => $this->deepLink(),
        ];
    }
}
