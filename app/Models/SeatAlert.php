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
    'watch_key',
    'departure_date',
    'flight_number',
    'offer_id',
    'cabin',
    'min_seats',
    'last_triggered_seats',
    'is_active',
    'last_triggered_at',
    'last_reminded_at',
])]
class SeatAlert extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'min_seats' => 'integer',
            'last_triggered_seats' => 'integer',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'last_reminded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function watchKeyFor(
        string $routeKey,
        ?string $flightNumber = null,
        ?string $offerId = null,
        ?string $cabin = null,
    ): string {
        return implode('|', [
            $routeKey,
            $flightNumber !== null && $flightNumber !== '' ? strtoupper($flightNumber) : '*',
            $offerId !== null && $offerId !== '' ? $offerId : '*',
            $cabin !== null && $cabin !== '' ? strtolower($cabin) : '*',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function notificationPayload(?int $availableSeats = null): array
    {
        $seats = $availableSeats ?? $this->last_triggered_seats;

        return [
            'user_name' => $this->user?->full_name ?: $this->user?->name ?: 'Customer',
            'origin' => $this->origin,
            'destination' => $this->destination,
            'route' => $this->origin.' → '.$this->destination,
            'departure_date' => $this->departure_date?->toFormattedDateString() ?: '',
            'flight_number' => $this->flight_number ?: '',
            'offer_id' => $this->offer_id ?: '',
            'cabin' => $this->cabin ?: '',
            'seats' => $seats !== null ? (string) $seats : '—',
            'min_seats' => (string) $this->min_seats,
            'deep_link' => $this->deepLink(),
            'notification_type' => 'tag',
        ];
    }

    public function deepLink(): string
    {
        if (is_string($this->offer_id) && $this->offer_id !== '') {
            $query = http_build_query(array_filter([
                'offer_id' => $this->offer_id,
                'origin' => $this->origin,
                'destination' => $this->destination,
                'date' => $this->departure_date?->toDateString(),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            return '/flights?'.$query;
        }

        $query = http_build_query(array_filter([
            'origin' => $this->origin,
            'destination' => $this->destination,
            'date' => $this->departure_date?->toDateString(),
            'flight_number' => $this->flight_number,
            'cabin' => $this->cabin,
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
            'flight_number' => $this->flight_number,
            'offer_id' => $this->offer_id,
            'cabin' => $this->cabin,
            'min_seats' => (int) $this->min_seats,
            'is_active' => $this->is_active,
            'deep_link' => $this->deepLink(),
        ];
    }
}
