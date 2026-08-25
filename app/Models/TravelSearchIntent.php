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
    'return_date',
    'last_seen_price',
    'previous_seen_price',
    'currency',
    'last_searched_at',
    'abandoned_notified_at',
    'price_drop_notified_at',
    'converted_at',
    'search_count',
    'results_viewed_at',
])]
class TravelSearchIntent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'last_seen_price' => 'decimal:2',
            'previous_seen_price' => 'decimal:2',
            'last_searched_at' => 'datetime',
            'abandoned_notified_at' => 'datetime',
            'price_drop_notified_at' => 'datetime',
            'converted_at' => 'datetime',
            'search_count' => 'integer',
            'results_viewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function notificationPayload(): array
    {
        $price = $this->last_seen_price !== null
            ? number_format((float) $this->last_seen_price, 0, '.', ',')
            : '—';

        return [
            'user_name' => $this->user?->full_name ?: $this->user?->name ?: 'Customer',
            'origin' => $this->origin,
            'destination' => $this->destination,
            'route' => $this->origin.' → '.$this->destination,
            'departure_date' => $this->departure_date?->toFormattedDateString() ?: '',
            'price' => $price,
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
            'return_date' => $this->return_date?->toDateString(),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        return '/flights?'.$query;
    }

    public static function routeKeyFor(string $origin, string $destination, ?string $departureDate): string
    {
        return strtolower(trim($origin)).'|'.strtolower(trim($destination)).'|'.($departureDate ?: '*');
    }
}
