<?php

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorite>
 */
class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => Favorite::TYPE_FLIGHT,
            'item_key' => 'offer_'.fake()->unique()->bothify('??####'),
            'status' => Favorite::STATUS_ACTIVE,
            'snapshot' => [
                'origin' => 'DXB',
                'destination' => 'LHR',
                'departure_at' => now()->addMonth()->toIso8601String(),
                'arrival_at' => now()->addMonth()->addHours(7)->toIso8601String(),
                'airline_code' => 'EK',
                'flight_number' => '215',
                'price_total' => '520.00',
                'currency' => 'USD',
                'one_way' => true,
                'duration' => 'PT7H15M',
            ],
            'search_context' => [
                'offer_id' => fake()->uuid(),
                'offer_key' => fake()->uuid(),
                'booking_provider_code' => 'EK',
            ],
            'expires_at' => now()->addDay(),
        ];
    }

    /**
     * Configure the favorite as a hotel.
     */
    public function hotel(): static
    {
        return $this->state(function (array $attributes): array {
            $hotelId = (string) fake()->numberBetween(100, 999);
            $cityId = (string) fake()->numberBetween(100, 999);

            return [
                'type' => Favorite::TYPE_HOTEL,
                'item_key' => "hotel_{$hotelId}_city_{$cityId}",
                'status' => Favorite::STATUS_ACTIVE,
                'snapshot' => [
                    'hotel_id' => $hotelId,
                    'city_id' => $cityId,
                    'name' => fake()->company().' Hotel',
                    'city' => 'Dubai',
                    'country' => 'UAE',
                    'rating' => 8.9,
                    'price_per_night' => 150.0,
                    'currency' => 'USD',
                    'image_url' => 'https://example.com/hotel.jpg',
                    'location' => 'Downtown',
                ],
                'search_context' => [
                    'hotel_id' => $hotelId,
                    'city_id' => $cityId,
                    'check_in' => now()->addWeek()->toDateString(),
                    'check_out' => now()->addWeek()->addDays(3)->toDateString(),
                    'guests' => 2,
                ],
                'expires_at' => null,
            ];
        });
    }

    /**
     * Mark the favorite as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Favorite::STATUS_EXPIRED,
            'expires_at' => now()->subHour(),
        ]);
    }
}
