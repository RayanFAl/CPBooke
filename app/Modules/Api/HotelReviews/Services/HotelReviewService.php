<?php

namespace App\Modules\Api\HotelReviews\Services;

use App\Models\HotelReview;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class HotelReviewService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createForUser(User $user, array $data, ?string $routeBookingId = null): HotelReview
    {
        $order = $this->resolveHotelOrder(
            $user,
            $routeBookingId,
            isset($data['booking_reference']) ? (string) $data['booking_reference'] : null,
            isset($data['booking_id']) ? (string) $data['booking_id'] : null,
        );

        if (HotelReview::query()->where('order_id', $order->id)->exists()) {
            throw ValidationException::withMessages([
                'booking_reference' => ['This booking has already been reviewed.'],
            ])->status(409);
        }

        $hotelIdFromOrder = $this->hotelIdFromOrder($order);
        $hotelId = isset($data['hotel_id']) ? (string) $data['hotel_id'] : $hotelIdFromOrder;

        if ($hotelId === null || $hotelId === '') {
            throw ValidationException::withMessages([
                'hotel_id' => ['Unable to resolve hotel_id for this booking.'],
            ]);
        }

        if ($hotelIdFromOrder !== null && $hotelIdFromOrder !== '' && $hotelId !== $hotelIdFromOrder) {
            throw ValidationException::withMessages([
                'hotel_id' => ['hotel_id does not match this booking.'],
            ]);
        }

        return HotelReview::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'hotel_id' => $hotelId,
            'booking_reference' => $order->booking_reference
                ?: ($order->external_booking_id ?: (string) ($data['booking_reference'] ?? '')),
            'overall_rating' => (int) $data['overall_rating'],
            'categories' => $this->normalizeCategories($data['categories'] ?? null),
            'comment' => isset($data['comment']) && is_string($data['comment']) && trim($data['comment']) !== ''
                ? trim($data['comment'])
                : null,
        ]);
    }

    public function findForBooking(User $user, string $bookingId): ?HotelReview
    {
        $order = $this->resolveHotelOrder($user, $bookingId, $bookingId, $bookingId);

        return HotelReview::query()
            ->where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, HotelReview>
     */
    public function paginateForHotel(string $hotelId, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return HotelReview::query()
            ->where('hotel_id', $hotelId)
            ->orderByDesc('id')
            ->paginate(
                perPage: $perPage,
                page: $page,
            );
    }

    /**
     * @param  LengthAwarePaginator<int, HotelReview>  $paginator
     * @return array<string, mixed>
     */
    public function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function resolveHotelOrder(
        User $user,
        ?string $routeBookingId = null,
        ?string $bookingReference = null,
        ?string $bookingId = null,
    ): Order {
        $candidates = array_values(array_unique(array_filter([
            $routeBookingId,
            $bookingReference,
            $bookingId,
        ], static fn (?string $value): bool => is_string($value) && trim($value) !== '')));

        if ($candidates === []) {
            throw ValidationException::withMessages([
                'booking_reference' => ['A booking reference or booking id is required.'],
            ]);
        }

        $query = Order::query()
            ->where('customer_id', $user->id)
            ->where('service_type', Order::SERVICE_TYPE_HOTEL)
            ->where(function (Builder $builder) use ($candidates): void {
                $builder
                    ->whereIn('external_booking_id', $candidates)
                    ->orWhereIn('booking_reference', $candidates)
                    ->orWhereIn('id', array_filter($candidates, static fn (string $value): bool => ctype_digit($value)));
            });

        $order = $query->first();

        if ($order === null) {
            throw ValidationException::withMessages([
                'booking_reference' => ['Hotel booking not found for this account.'],
            ])->status(404);
        }

        return $order;
    }

    public function hotelIdFromOrder(Order $order): ?string
    {
        $details = is_array($order->details) ? $order->details : [];
        $hotelId = $details['hotel_id'] ?? null;

        return is_string($hotelId) || is_numeric($hotelId) ? (string) $hotelId : null;
    }

    /**
     * @param  mixed  $categories
     * @return array<string, int>|null
     */
    private function normalizeCategories(mixed $categories): ?array
    {
        if (! is_array($categories) || $categories === []) {
            return null;
        }

        $normalized = [];

        foreach (HotelReview::CATEGORY_KEYS as $key) {
            if (! array_key_exists($key, $categories)) {
                continue;
            }

            $normalized[$key] = (int) $categories[$key];
        }

        return $normalized === [] ? null : $normalized;
    }
}
