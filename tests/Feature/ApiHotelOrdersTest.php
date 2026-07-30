<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiHotelOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_hotel_creates_order_and_returns_hotel_details(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedHotelProviderWallet();

        Sanctum::actingAs($customer);

        $payload = $this->hotelPayload();

        $response = $this->postJson('/api/v1/orders/sync-hotel', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Order saved successfully.')
            ->assertJsonPath('data.id', 'BN-HTL-123456')
            ->assertJsonPath('data.product_type', 'hotel')
            ->assertJsonPath('data.service_type', 'hotel')
            ->assertJsonPath('data.cpbooke_id', 1)
            ->assertJsonPath('data.external_booking_id', 'BN-HTL-123456')
            ->assertJsonPath('data.provider_booking.booking_id', 'BN-HTL-123456')
            ->assertJsonPath('data.provider_booking.provider', 'booknow_hotels')
            ->assertJsonPath('data.hotel.hotel_id', 'h_1001')
            ->assertJsonPath('data.hotel.hotel_name', 'Rixos Premium Tripoli')
            ->assertJsonPath('data.hotel.check_in', '2026-08-10')
            ->assertJsonPath('data.hotel.check_out', '2026-08-12')
            ->assertJsonPath('data.hotel.room_name', 'Deluxe Twin')
            ->assertJsonPath('data.items.0.item_details.hotel_id', 'h_1001')
            ->assertJsonPath('data.guests.0.first_name', 'Ahmed')
            ->assertJsonPath('meta.created', true)
            ->assertJsonPath('meta.idempotent', false);

        $orderNumber = (string) $response->json('data.number');
        $this->assertNotSame('', $orderNumber);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'external_booking_id' => 'BN-HTL-123456',
            'booking_reference' => $orderNumber,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'total_amount' => '850.00',
        ]);

        $this->assertDatabaseHas('providers', [
            'key' => Provider::KEY_BOOKNOW_HOTELS,
        ]);
    }

    public function test_sync_hotel_is_idempotent_by_booking_id(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedHotelProviderWallet();

        Sanctum::actingAs($customer);

        $payload = $this->hotelPayload();

        $this->postJson('/api/v1/orders/sync-hotel', $payload)->assertCreated();

        $payload['grand_total'] = 900.00;
        $payload['items'][0]['unit_price'] = 900.00;
        $payload['payment']['amount'] = 900.00;
        $payload['items'][0]['item_details']['room_name'] = 'Executive Suite';

        $this->postJson('/api/v1/orders/sync-hotel', $payload)
            ->assertOk()
            ->assertJsonPath('data.grand_total', '900.00')
            ->assertJsonPath('data.hotel.room_name', 'Executive Suite')
            ->assertJsonPath('meta.idempotent', true);

        $this->assertSame(1, Order::query()->where('external_booking_id', 'BN-HTL-123456')->count());
    }

    public function test_sync_hotel_can_update_status_to_cancelled(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedHotelProviderWallet();

        Sanctum::actingAs($customer);

        $payload = $this->hotelPayload();
        $this->postJson('/api/v1/orders/sync-hotel', $payload)->assertCreated();

        $payload['status'] = 'cancelled';
        $payload['payment']['status'] = 'refunded';

        $this->postJson('/api/v1/orders/sync-hotel', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('meta.idempotent', true);

        $this->assertDatabaseHas('orders', [
            'external_booking_id' => 'BN-HTL-123456',
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
        ]);
    }

    public function test_get_orders_supports_product_type_hotel_filter(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedHotelProviderWallet();

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/orders/sync-hotel', $this->hotelPayload())->assertCreated();

        $this->getJson('/api/v1/orders?product_type=hotel')
            ->assertOk()
            ->assertJsonPath('data.orders.0.product_type', 'hotel')
            ->assertJsonPath('data.orders.0.service_type', 'hotel')
            ->assertJsonPath('data.orders.0.hotel.hotel_id', 'h_1001')
            ->assertJsonPath('data.orders.0.hotel.check_in', '2026-08-10')
            ->assertJsonPath('data.orders.0.provider_booking.booking_id', 'BN-HTL-123456')
            ->assertJsonPath('data.orders.0.items.0.item_details.hotel_name', 'Rixos Premium Tripoli');

        $orderId = (int) Order::query()->value('id');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.product_type', 'hotel')
            ->assertJsonPath('data.external_booking_id', 'BN-HTL-123456')
            ->assertJsonPath('data.hotel.room_name', 'Deluxe Twin')
            ->assertJsonPath('data.guests.0.last_name', 'Ali')
            ->assertJsonPath('data.items.0.item_details.nights', 2);
    }

    public function test_sync_hotel_requires_auth(): void
    {
        $this->postJson('/api/v1/orders/sync-hotel', $this->hotelPayload())
            ->assertUnauthorized();
    }

    public function test_sync_hotel_requires_booking_id(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $payload = $this->hotelPayload();
        unset($payload['provider_booking']['booking_id']);

        $this->postJson('/api/v1/orders/sync-hotel', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['provider_booking.booking_id']);
    }

    public function test_sync_hotel_requires_hotel_item_details(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $payload = $this->hotelPayload();
        unset($payload['items'][0]['item_details']['hotel_id']);
        unset($payload['items'][0]['item_details']['check_in']);

        $this->postJson('/api/v1/orders/sync-hotel', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.item_details.hotel_id',
                'items.0.item_details.check_in',
            ]);
    }

    private function seedHotelProviderWallet(): void
    {
        $provider = Provider::query()->create([
            'name' => 'BookNow Hotels',
            'key' => Provider::KEY_BOOKNOW_HOTELS,
            'status' => Provider::STATUS_ACTIVE,
        ]);

        ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => ProviderWallet::ENVIRONMENT_PRODUCTION,
            'balance' => '5000.00',
            'allow_negative' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function hotelPayload(): array
    {
        return [
            'source' => 'mobile_app',
            'product_type' => 'hotel',
            'status' => 'confirmed',
            'currency' => 'LYD',
            'grand_total' => 850.0,
            'base_amount' => 800.0,
            'tax_amount' => 50.0,
            'contact' => [
                'name' => 'Ahmed Ali',
                'first_name' => 'Ahmed',
                'last_name' => 'Ali',
                'email' => 'ahmed@example.com',
                'phone' => '+218912345678',
            ],
            'guests' => [
                [
                    'title' => 'Mr.',
                    'first_name' => 'Ahmed',
                    'last_name' => 'Ali',
                    'email' => 'ahmed@example.com',
                    'phone' => '+218912345678',
                ],
                [
                    'first_name' => 'Sara',
                    'last_name' => 'Ali',
                ],
            ],
            'provider_booking' => [
                'booking_id' => 'BN-HTL-123456',
                'provider' => 'booknow_hotels',
                'order_id' => '123456',
                'order_number' => 'HTL-98765',
                'booking_reference' => 'HTL-98765',
            ],
            'items' => [
                [
                    'type' => 'hotel',
                    'product_type' => 'hotel',
                    'title' => 'Rixos Premium Tripoli',
                    'quantity' => 1,
                    'unit_price' => 850.0,
                    'currency' => 'LYD',
                    'item_details' => [
                        'hotel_id' => 'h_1001',
                        'hotel_name' => 'Rixos Premium Tripoli',
                        'city_id' => 'c_12',
                        'city_name' => 'Tripoli',
                        'country' => 'LY',
                        'source' => 'provider_x',
                        'offer_id' => 'off_55',
                        'room_name' => 'Deluxe Twin',
                        'room_type' => 'deluxe',
                        'board' => 'BB',
                        'check_in' => '2026-08-10',
                        'check_out' => '2026-08-12',
                        'nights' => 2,
                        'rooms' => 1,
                        'adults' => 2,
                        'children' => 0,
                        'guests_count' => 2,
                        'stars' => 5,
                        'address' => 'Tripoli Corniche',
                        'image_url' => 'https://example.com/rixos.jpg',
                        'guests' => [
                            [
                                'title' => 'Mr.',
                                'first_name' => 'Ahmed',
                                'last_name' => 'Ali',
                                'email' => 'ahmed@example.com',
                                'phone' => '+218912345678',
                            ],
                        ],
                    ],
                ],
            ],
            'payment' => [
                'status' => 'paid',
                'method' => 'card',
                'method_code' => 1,
                'amount' => 850.0,
                'currency' => 'LYD',
                'transaction_id' => 'htl_123456',
                'paid_at' => '2026-07-29T12:00:00Z',
            ],
            'metadata' => [
                'source_screen' => 'mobile_app',
                'booknow_booking_id' => 'BN-HTL-123456',
            ],
        ];
    }
}
