<?php

namespace Tests\Feature;

use App\Models\HotelReview;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HotelReviewsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_submit_hotel_review_once(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $order = $this->makeHotelOrder($customer, 'BN-HTL-REV-1', 'HTL-456');

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/hotel-reviews', [
            'booking_reference' => 'BN-HTL-REV-1',
            'hotel_id' => 'HTL-456',
            'overall_rating' => 5,
            'categories' => [
                'cleanliness' => 5,
                'location' => 4,
                'service' => 5,
                'comfort' => 4,
                'value' => 5,
            ],
            'comment' => 'إقامة ممتازة',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hotel_id', 'HTL-456')
            ->assertJsonPath('data.overall_rating', 5)
            ->assertJsonPath('data.categories.cleanliness', 5);

        $this->postJson('/api/v1/hotel-reviews', [
            'booking_reference' => 'BN-HTL-REV-1',
            'hotel_id' => 'HTL-456',
            'overall_rating' => 4,
        ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'already_reviewed');

        $this->assertSame(1, HotelReview::query()->count());
        $this->assertDatabaseHas('hotel_reviews', [
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'hotel_id' => 'HTL-456',
        ]);
    }

    public function test_booking_review_endpoint_and_order_payload_include_user_review(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $order = $this->makeHotelOrder($customer, 'BN-HTL-REV-2', 'HTL-789');

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/hotels/bookings/BN-HTL-REV-2/review')
            ->assertOk()
            ->assertJsonPath('data.review', null);

        $this->postJson('/api/v1/hotels/bookings/BN-HTL-REV-2/reviews', [
            'overall_rating' => 4,
            'comment' => 'Nice stay',
        ])
            ->assertCreated()
            ->assertJsonPath('data.overall_rating', 4);

        $this->getJson('/api/v1/hotels/bookings/BN-HTL-REV-2/review')
            ->assertOk()
            ->assertJsonPath('data.review.overall_rating', 4)
            ->assertJsonPath('data.review.hotel_id', 'HTL-789');

        $this->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.user_review.overall_rating', 4)
            ->assertJsonPath('data.user_review.hotel_id', 'HTL-789');
    }

    public function test_hotel_reviews_list_is_public(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $order = $this->makeHotelOrder($customer, 'BN-HTL-REV-3', 'HTL-PUBLIC');

        HotelReview::query()->create([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'hotel_id' => 'HTL-PUBLIC',
            'booking_reference' => $order->booking_reference,
            'overall_rating' => 5,
            'categories' => ['cleanliness' => 5],
            'comment' => 'Great',
        ]);

        $this->getJson('/api/v1/hotels/HTL-PUBLIC/reviews')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hotel_id', 'HTL-PUBLIC')
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.reviews.0.overall_rating', 5);
    }

    private function makeHotelOrder(User $customer, string $externalId, string $hotelId): Order
    {
        return Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'booknow_hotels',
            'external_booking_id' => $externalId,
            'booking_reference' => 'CP-HTL-'.substr($externalId, -4),
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'currency' => 'LYD',
            'total_amount' => 250,
            'details' => [
                'hotel_id' => $hotelId,
                'hotel_name' => 'Test Hotel',
            ],
            'request_payload' => [],
            'response_payload' => [
                'id' => $externalId,
                'provider_booking' => [
                    'booking_id' => $externalId,
                    'provider' => 'booknow_hotels',
                ],
            ],
        ]);
    }
}
