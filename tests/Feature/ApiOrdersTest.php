<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function seedBooknowProviderWallet(float $balance = 10000): void
    {
        $provider = Provider::query()->firstOrCreate(
            ['key' => Provider::KEY_BOOKNOW],
            [
                'name' => 'BookNow',
                'status' => Provider::STATUS_ACTIVE,
            ],
        );

        ProviderWallet::query()->firstOrCreate(
            [
                'provider_id' => $provider->id,
                'currency' => 'LYD',
                'environment' => ProviderWallet::ENVIRONMENT_PRODUCTION,
            ],
            [
                'balance' => number_format($balance, 2, '.', ''),
                'allow_negative' => true,
                'is_active' => true,
            ],
        );
    }

    public function test_customer_can_create_a_flight_order(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/orders', [
            'provider_name' => 'Booke Provider',
            'currency' => 'usd',
            'total_amount' => 199.99,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => [
                'passenger_name' => 'Rakan Alhemmal',
                'airline' => 'Saudia',
                'pnr' => 'PNR12345',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.status', Order::STATUS_PENDING_PAYMENT)
            ->assertJsonPath('data.order.payment_status', Order::PAYMENT_STATUS_UNPAID)
            ->assertJsonPath('data.order.service_type', Order::SERVICE_TYPE_FLIGHT)
            ->assertJsonPath('data.order.details.airline', 'Saudia')
            ->assertJsonMissingPath('data.order.internal_notes');

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'provider_name' => 'Booke Provider',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
        ]);

        $orderId = (int) $response->json('data.order.id');

        $this->assertDatabaseHas('financial_transactions', [
            'order_id' => $orderId,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '199.99',
            'currency' => Order::DEFAULT_CURRENCY,
            'source' => FinancialTransaction::SOURCE_ORDER_CREATION,
        ]);

        $order = Order::query()->findOrFail($orderId);

        $this->assertSame(Order::DEFAULT_CURRENCY, $order->currency);

        FinancialTransaction::query()->firstOrCreate(
            [
                'order_id' => $order->id,
                'type' => FinancialTransaction::TYPE_PAYMENT,
                'source' => FinancialTransaction::SOURCE_ORDER_CREATION,
            ],
            [
                'amount' => $order->total_amount,
                'currency' => $order->currency,
            ],
        );

        $this->assertSame(1, FinancialTransaction::query()->where('order_id', $orderId)->count());
    }

    public function test_customer_can_create_a_hotel_order(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/orders', [
            'provider_name' => 'Hotel Provider',
            'currency' => 'USD',
            'total_amount' => 80.00,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => [
                'hotel_name' => 'Booke Palace',
                'check_in' => '2026-06-14',
                'check_out' => '2026-06-18',
                'guests' => 2,
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.order.status', Order::STATUS_PENDING_PAYMENT)
            ->assertJsonPath('data.order.payment_status', Order::PAYMENT_STATUS_UNPAID)
            ->assertJsonPath('data.order.service_type', Order::SERVICE_TYPE_HOTEL)
            ->assertJsonPath('data.order.details.hotel_name', 'Booke Palace');

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
        ]);
    }

    public function test_customer_can_create_an_insurance_order(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/orders', [
            'provider_name' => 'Insurance Provider',
            'currency' => 'USD',
            'total_amount' => 45.00,
            'service_type' => Order::SERVICE_TYPE_INSURANCE,
            'details' => [
                'insurance_type' => 'travel_medical',
                'coverage_days' => 14,
                'beneficiary_name' => 'Maha Alotaibi',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.order.status', Order::STATUS_PENDING_PAYMENT)
            ->assertJsonPath('data.order.payment_status', Order::PAYMENT_STATUS_UNPAID)
            ->assertJsonPath('data.order.service_type', Order::SERVICE_TYPE_INSURANCE)
            ->assertJsonPath('data.order.details.coverage_days', 14);
    }

    public function test_customer_only_sees_their_own_orders_and_never_receives_internal_notes(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $otherCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ownedOrder = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Primary Provider',
            'booking_reference' => 'BK-SELF-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Booke Suites'],
            'currency' => 'USD',
            'total_amount' => 50.00,
            'internal_notes' => 'Admin only note',
            'request_payload' => ['hotel_name' => 'Booke Suites'],
        ]);

        $foreignOrder = Order::query()->create([
            'customer_id' => $otherCustomer->id,
            'provider_name' => 'Foreign Provider',
            'booking_reference' => 'BK-OTHER-001',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['airline' => 'Flynas'],
            'currency' => 'USD',
            'total_amount' => 90.00,
            'request_payload' => ['airline' => 'Flynas'],
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.booking_reference', 'BK-SELF-001')
            ->assertJsonMissingPath('data.orders.0.internal_notes');

        $this->getJson("/api/v1/orders/{$ownedOrder->id}")
            ->assertOk()
            ->assertJsonPath('data.order.booking_reference', 'BK-SELF-001')
            ->assertJsonMissingPath('data.order.internal_notes');

        $this->getJson("/api/v1/orders/{$foreignOrder->id}")
            ->assertForbidden();
    }

    public function test_admin_account_cannot_use_customer_order_api(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/orders', [
            'provider_name' => 'Blocked Provider',
            'currency' => 'USD',
            'total_amount' => 10.00,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => [
                'passenger_name' => 'Blocked Admin',
                'airline' => 'Booke Air',
                'pnr' => 'BLOCK123',
            ],
        ])->assertForbidden();

        $this->getJson('/api/v1/orders')->assertForbidden();
    }

    public function test_customer_can_sync_booknow_order_after_flight_book(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'email' => 'a.rayan@median.ly',
            'phone' => '+218943215277',
        ]);

        $this->seedBooknowProviderWallet();
        Sanctum::actingAs($customer);

        $payload = $this->booknowOrderPayload();

        $response = $this->postJson('/api/v1/orders/sync-flight', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Order saved successfully.')
            ->assertJsonPath('data.id', '01ktgspkyr6ma0fjqjc7vexyry')
            ->assertJsonPath('data.provider_order_number', 'WFQ0001OZ')
            ->assertJsonPath('data.cpbooke_id', 1)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.items.0.provider_reference', 'AAXKDO')
            ->assertJsonPath('data.contact.email', 'a.rayan@median.ly');

        $orderNumber = (string) $response->json('data.number');
        $this->assertSame('CP0001BA', $orderNumber);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'external_booking_id' => '01ktgspkyr6ma0fjqjc7vexyry',
            'booking_reference' => 'CP0001BA',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'source' => 'mobile_app',
        ]);
    }

    public function test_booknow_order_sync_persists_tax_amount(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedBooknowProviderWallet();
        Sanctum::actingAs($customer);

        $payload = $this->booknowOrderPayload();
        $payload['base_amount'] = 613.00;
        $payload['tax_amount'] = 127.00;
        $payload['grand_total'] = 740.00;
        $payload['payment']['amount'] = 740.00;

        $this->postJson('/api/v1/orders/sync-flight', $payload)
            ->assertCreated()
            ->assertJsonPath('data.grand_total', '740.00')
            ->assertJsonPath('data.base_amount', '613.00')
            ->assertJsonPath('data.tax_amount', '127.00');

        $this->assertDatabaseHas('orders', [
            'external_booking_id' => '01ktgspkyr6ma0fjqjc7vexyry',
            'total_amount' => '740.00',
            'base_amount' => '613.00',
            'tax_amount' => '127.00',
        ]);

        $orderId = (int) Order::query()->where('external_booking_id', '01ktgspkyr6ma0fjqjc7vexyry')->value('id');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.grand_total', '740.00')
            ->assertJsonPath('data.base_amount', '613.00')
            ->assertJsonPath('data.tax_amount', '127.00');
    }

    public function test_booknow_order_sync_is_idempotent_by_booking_id(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedBooknowProviderWallet();
        Sanctum::actingAs($customer);

        $payload = $this->booknowOrderPayload();

        $this->postJson('/api/v1/orders/sync-flight', $payload)->assertCreated();

        $payload['grand_total'] = 610.00;
        $payload['payment']['amount'] = 610.00;

        $this->postJson('/api/v1/orders/sync-flight', $payload)
            ->assertOk()
            ->assertJsonPath('data.grand_total', '610.00');

        $this->assertSame(1, Order::query()->where('external_booking_id', '01ktgspkyr6ma0fjqjc7vexyry')->count());
    }

    public function test_booknow_order_show_returns_booknow_shape(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedBooknowProviderWallet();
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/orders/sync-flight', $this->booknowOrderPayload())->assertCreated();
        $orderId = (int) Order::query()->where('external_booking_id', '01ktgspkyr6ma0fjqjc7vexyry')->value('id');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.id', '01ktgspkyr6ma0fjqjc7vexyry')
            ->assertJsonPath('data.number', 'CP0001BA')
            ->assertJsonPath('data.provider_order_number', 'WFQ0001OZ')
            ->assertJsonPath('data.booking_flight_data.departure_airport', 'MJI')
            ->assertJsonPath('data.booking_flight_data.arrival_airport', 'TUN')
            ->assertJsonPath('data.details.origin', 'MJI')
            ->assertJsonPath('data.details.destination', 'TUN')
            ->assertJsonPath('data.items.0.item_details.segments.0.flight_number', 'BM0400')
            ->assertJsonMissingPath('data.order');
    }

    public function test_booknow_order_show_returns_booking_flight_data_from_sync_payload(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedBooknowProviderWallet();
        Sanctum::actingAs($customer);

        $payload = $this->booknowOrderPayload();
        $payload['booking_flight_data'] = [
            'departure_airport' => 'MJI',
            'arrival_airport' => 'BEN',
            'departure_time' => '2026-07-05 23:10:00',
            'segments' => [
                [
                    'flight_number' => 'BM0400',
                    'departure_airport' => 'MJI',
                    'arrival_airport' => 'BEN',
                    'departure_time' => '2026-07-05 23:10:00',
                    'arrival_time' => '2026-07-06 00:10:00',
                ],
            ],
        ];

        $this->postJson('/api/v1/orders/sync-flight', $payload)->assertCreated();
        $orderId = (int) Order::query()->where('external_booking_id', '01ktgspkyr6ma0fjqjc7vexyry')->value('id');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.booking_flight_data.departure_airport', 'MJI')
            ->assertJsonPath('data.booking_flight_data.arrival_airport', 'BEN')
            ->assertJsonPath('data.booking_flight_data.departure_time', '2026-07-05 23:10:00')
            ->assertJsonPath('data.details.origin', 'MJI')
            ->assertJsonPath('data.details.destination', 'BEN');
    }

    public function test_sync_flight_rejects_customer_id_from_request_body(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $otherCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedBooknowProviderWallet();
        Sanctum::actingAs($customer);

        $payload = $this->booknowOrderPayload();
        $payload['customer_id'] = $otherCustomer->id;

        $this->postJson('/api/v1/orders/sync-flight', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_sync_flight_idempotent_retry_returns_same_order(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedBooknowProviderWallet();
        Sanctum::actingAs($customer);

        $payload = $this->booknowOrderPayload();

        $this->postJson('/api/v1/orders/sync-flight', $payload)->assertCreated();
        $this->postJson('/api/v1/orders/sync-flight', $payload)
            ->assertOk()
            ->assertJsonPath('meta.idempotent', true);

        $this->assertSame(1, Order::query()->where('external_booking_id', '01ktgspkyr6ma0fjqjc7vexyry')->count());
        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'external_booking_id' => '01ktgspkyr6ma0fjqjc7vexyry',
        ]);
    }

    public function test_booknow_status_ticketed_maps_to_internal_ticketed(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedBooknowProviderWallet();
        Sanctum::actingAs($customer);

        $payload = $this->booknowOrderPayload();
        $payload['status'] = 'ticketed';

        $this->postJson('/api/v1/orders/sync-flight', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'ticketed')
            ->assertJsonPath('data.internal_status', Order::STATUS_TICKETED);

        $this->assertDatabaseHas('orders', [
            'external_booking_id' => '01ktgspkyr6ma0fjqjc7vexyry',
            'status' => Order::STATUS_TICKETED,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function booknowOrderPayload(): array
    {
        return [
            'source' => 'mobile_app',
            'product_type' => 'flight',
            'status' => 'confirmed',
            'currency' => 'LYD',
            'grand_total' => 590.00,
            'provider_booking' => [
                'booking_id' => '01ktgspkyr6ma0fjqjc7vexyry',
                'order_number' => 'WFQ0001OZ',
                'pnr' => 'AAXKDO',
                'provider_id' => 12,
                'provider_name' => 'Buraq Air',
                'search_uuid' => 'search-uuid-123',
            ],
            'contact' => [
                'first_name' => 'RAYAN',
                'last_name' => 'FATHI',
                'email' => 'a.rayan@median.ly',
                'phone' => '+218943215277',
            ],
            'passengers' => [
                [
                    'type' => 'adult',
                    'title' => 'Mr',
                    'first_name' => 'RAYAN',
                    'last_name' => 'FATHI',
                    'dob' => '1998-05-10',
                    'gender' => 'M',
                    'nationality' => 'LY',
                    'passport_number' => 'AB1234567',
                    'passport_expiry' => '2030-01-01',
                    'passport_issue_country' => 'LY',
                ],
            ],
            'items' => [
                [
                    'type' => 'flight',
                    'product_type' => 'ticket',
                    'product_subtype' => 'oneway',
                    'provider_reference' => 'AAXKDO',
                    'total' => 590.00,
                    'currency' => 'LYD',
                    'item_details' => [
                        'pnr' => 'AAXKDO',
                        'airline_code' => 'BM',
                        'airline_name' => 'Buraq Air',
                        'segments' => [
                            [
                                'flight_number' => 'BM0400',
                                'departure_airport' => 'MJI',
                                'arrival_airport' => 'TUN',
                                'departure_time' => '2026-06-20 10:25:00',
                                'arrival_time' => '2026-06-20 10:35:00',
                                'duration' => 10,
                                'cabin_type' => 'Y',
                                'class' => 'S',
                            ],
                        ],
                        'passengers' => [],
                    ],
                ],
            ],
            'payment' => [
                'status' => 'paid',
                'method' => 'card',
                'method_code' => 1,
                'amount' => 590.00,
                'currency' => 'LYD',
                'transaction_id' => 'txn_123',
                'paid_at' => '2026-06-07T10:22:50Z',
            ],
            'metadata' => [
                'app_version' => '1.0.0',
                'platform' => 'android',
            ],
            'booking_flight_data' => [
                'departure_airport' => 'MJI',
                'arrival_airport' => 'TUN',
                'departure_time' => '2026-06-20 10:25:00',
                'segments' => [
                    [
                        'flight_number' => 'BM0400',
                        'departure_airport' => 'MJI',
                        'arrival_airport' => 'TUN',
                        'departure_time' => '2026-06-20 10:25:00',
                        'arrival_time' => '2026-06-20 10:35:00',
                        'duration' => 10,
                        'cabin_type' => 'Y',
                        'class' => 'S',
                    ],
                ],
            ],
        ];
    }
}