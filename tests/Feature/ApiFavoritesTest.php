<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiFavoritesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function flightPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => Favorite::TYPE_FLIGHT,
            'item_key' => 'offer_ek_215_dxb_lhr',
            'snapshot' => [
                'origin' => 'DXB',
                'destination' => 'LHR',
                'departure_at' => '2026-08-01T10:00:00',
                'arrival_at' => '2026-08-01T18:00:00',
                'airline_code' => 'EK',
                'flight_number' => '215',
                'price_total' => '520.00',
                'currency' => 'USD',
                'one_way' => true,
                'duration' => 'PT7H15M',
            ],
            'search_context' => [
                'offer_id' => 'offer-1',
                'offer_key' => 'key-1',
                'booking_session_uuid' => 'session-1',
                'booking_provider_id' => 1,
                'booking_provider_code' => 'EK',
                'last_ticketing_date' => '2026-07-09',
            ],
            'expires_at' => now()->addDay()->toIso8601String(),
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function hotelPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => Favorite::TYPE_HOTEL,
            'item_key' => 'hotel_123_city_456',
            'snapshot' => [
                'hotel_id' => '123',
                'city_id' => '456',
                'name' => 'Luxury Hotel',
                'city' => 'Dubai',
                'country' => 'UAE',
                'rating' => 8.9,
                'price_per_night' => 150.0,
                'currency' => 'USD',
                'image_url' => 'https://example.com/hotel.jpg',
                'location' => 'Downtown',
            ],
            'search_context' => [
                'hotel_id' => '123',
                'city_id' => '456',
                'check_in' => '2026-08-01',
                'check_out' => '2026-08-05',
                'guests' => 2,
            ],
        ], $overrides);
    }

    private function actingAsCustomer(): User
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        return $customer;
    }

    public function test_customer_can_add_a_flight_favorite(): void
    {
        $customer = $this->actingAsCustomer();

        $response = $this->postJson('/api/v1/favorites', $this->flightPayload());

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Favorite saved successfully.')
            ->assertJsonPath('data.type', 'flight')
            ->assertJsonPath('data.item_key', 'offer_ek_215_dxb_lhr')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.snapshot.origin', 'DXB')
            ->assertJsonPath('data.search_context.offer_id', 'offer-1');

        $this->assertDatabaseHas('favorites', [
            'id' => $response->json('data.id'),
            'user_id' => $customer->id,
            'type' => 'flight',
            'item_key' => 'offer_ek_215_dxb_lhr',
            'status' => 'active',
        ]);
    }

    public function test_customer_can_add_a_hotel_favorite(): void
    {
        $this->actingAsCustomer();

        $this->postJson('/api/v1/favorites', $this->hotelPayload())
            ->assertCreated()
            ->assertJsonPath('data.type', 'hotel')
            ->assertJsonPath('data.item_key', 'hotel_123_city_456')
            ->assertJsonPath('data.snapshot.name', 'Luxury Hotel')
            ->assertJsonPath('data.expires_at', null);
    }

    public function test_re_adding_same_favorite_is_idempotent(): void
    {
        $this->actingAsCustomer();

        $first = $this->postJson('/api/v1/favorites', $this->flightPayload())
            ->assertCreated()
            ->json('data.id');

        $second = $this->postJson('/api/v1/favorites', $this->flightPayload())
            ->assertOk()
            ->assertJsonPath('message', 'Favorite already exists.')
            ->assertJsonPath('data.id', $first)
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_customer_can_list_favorites_with_filters_and_meta(): void
    {
        $customer = $this->actingAsCustomer();

        Favorite::factory()->for($customer)->create([
            'item_key' => 'flight_active',
            'status' => Favorite::STATUS_ACTIVE,
            'expires_at' => now()->addDay(),
        ]);

        Favorite::factory()->for($customer)->expired()->create([
            'item_key' => 'flight_expired',
        ]);

        Favorite::factory()->for($customer)->hotel()->create([
            'item_key' => 'hotel_123_city_456',
        ]);

        Favorite::factory()->create(); // other user

        $this->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.active_count', 2)
            ->assertJsonPath('meta.expired_count', 1);

        $this->getJson('/api/v1/favorites?type=flight&status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_key', 'flight_active')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.active_count', 1)
            ->assertJsonPath('meta.expired_count', 1);

        $this->getJson('/api/v1/favorites?type=hotel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'hotel');
    }

    public function test_list_auto_expires_stale_flights(): void
    {
        $customer = $this->actingAsCustomer();

        $favorite = Favorite::factory()->for($customer)->create([
            'item_key' => 'stale_offer',
            'status' => Favorite::STATUS_ACTIVE,
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/v1/favorites?status=expired')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $favorite->id)
            ->assertJsonPath('data.0.status', 'expired')
            ->assertJsonPath('meta.expired_count', 1);

        $this->assertDatabaseHas('favorites', [
            'id' => $favorite->id,
            'status' => Favorite::STATUS_EXPIRED,
        ]);
    }

    public function test_customer_can_check_favorite_status(): void
    {
        $customer = $this->actingAsCustomer();

        $favorite = Favorite::factory()->for($customer)->hotel()->create([
            'item_key' => 'hotel_123_city_456',
        ]);

        $this->getJson('/api/v1/favorites/check?type=hotel&item_key=hotel_123_city_456')
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true)
            ->assertJsonPath('data.favorite_id', $favorite->id);

        $this->getJson('/api/v1/favorites/check?type=hotel&item_key=hotel_999_city_000')
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false)
            ->assertJsonPath('data.favorite_id', null);
    }

    public function test_customer_can_delete_favorite_by_id(): void
    {
        $customer = $this->actingAsCustomer();

        $favorite = Favorite::factory()->for($customer)->create();

        $this->deleteJson("/api/v1/favorites/{$favorite->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Favorite removed successfully.');

        $this->assertDatabaseMissing('favorites', [
            'id' => $favorite->id,
        ]);
    }

    public function test_customer_can_delete_favorite_by_type_and_item_key(): void
    {
        $customer = $this->actingAsCustomer();

        Favorite::factory()->for($customer)->hotel()->create([
            'item_key' => 'hotel_123_city_456',
        ]);

        $this->deleteJson('/api/v1/favorites?type=hotel&item_key=hotel_123_city_456')
            ->assertOk()
            ->assertJsonPath('message', 'Favorite removed successfully.');

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_customer_cannot_delete_another_users_favorite(): void
    {
        $this->actingAsCustomer();

        $otherFavorite = Favorite::factory()->create();

        $this->deleteJson("/api/v1/favorites/{$otherFavorite->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('favorites', [
            'id' => $otherFavorite->id,
        ]);
    }

    public function test_admin_account_cannot_use_favorites(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/favorites')->assertForbidden();
        $this->postJson('/api/v1/favorites', $this->flightPayload())->assertForbidden();
    }

    public function test_guest_cannot_access_favorites(): void
    {
        $this->getJson('/api/v1/favorites')->assertUnauthorized();
        $this->postJson('/api/v1/favorites', $this->flightPayload())->assertUnauthorized();
    }

    public function test_enforces_max_favorites_per_type(): void
    {
        $customer = $this->actingAsCustomer();

        Favorite::factory()
            ->count(Favorite::MAX_PER_TYPE)
            ->for($customer)
            ->sequence(fn ($sequence) => [
                'item_key' => 'offer_'.$sequence->index,
            ])
            ->create();

        $this->postJson('/api/v1/favorites', $this->flightPayload([
            'item_key' => 'offer_overflow',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        // Hotels remain independent of the flight limit.
        $this->postJson('/api/v1/favorites', $this->hotelPayload())
            ->assertCreated();
    }

    public function test_creating_already_expired_flight_sets_expired_status(): void
    {
        $this->actingAsCustomer();

        $this->postJson('/api/v1/favorites', $this->flightPayload([
            'item_key' => 'expired_offer',
            'expires_at' => now()->subHour()->toIso8601String(),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'expired');
    }
}
