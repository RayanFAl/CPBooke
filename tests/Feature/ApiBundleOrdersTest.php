<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiBundleOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_bundle_creates_one_order_with_flight_esim_and_insurance_items(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedBooknowWallet();

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/orders/sync-bundle', $this->bundlePayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', 'BN-FLIGHT-123')
            ->assertJsonPath('data.product_type', 'flight')
            ->assertJsonPath('data.is_bundle', true)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.cpbooke_id', 1)
            ->assertJsonPath('data.flight.item_id', '11')
            ->assertJsonPath('data.seats.0.0', '12A')
            ->assertJsonPath('data.esims.0.item_id', 'esim-item-1')
            ->assertJsonPath('data.esims.0.qr', 'LPA:1$example.com$BUNDLE')
            ->assertJsonPath('data.insurances.0.item_id', '34')
            ->assertJsonPath('data.insurances.0.order_id', '12')
            ->assertJsonPath('data.insurances.0.ticket_number', 'CMP-58737')
            ->assertJsonPath('data.items.0.type', 'flight')
            ->assertJsonPath('data.items.1.type', 'esim')
            ->assertJsonPath('data.items.2.type', 'insurance')
            ->assertJsonPath('meta.created', true);

        $orderNumber = (string) $response->json('data.number');
        $this->assertNotSame('', $orderNumber);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'external_booking_id' => 'BN-FLIGHT-123',
            'booking_reference' => $orderNumber,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'total_amount' => '850.00',
        ]);

        $this->assertSame(1, Order::query()->count());
    }

    public function test_sync_bundle_is_idempotent_by_flight_booking_id(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedBooknowWallet();

        Sanctum::actingAs($customer);

        $payload = $this->bundlePayload();

        $this->postJson('/api/v1/orders/sync-bundle', $payload)->assertCreated();

        $payload['grand_total'] = 900.0;
        $payload['items'][0]['unit_price'] = 650.0;

        $this->postJson('/api/v1/orders/sync-bundle', $payload)
            ->assertOk()
            ->assertJsonPath('data.grand_total', '900.00')
            ->assertJsonPath('data.is_bundle', true)
            ->assertJsonPath('meta.idempotent', true);

        $this->assertSame(1, Order::query()->where('external_booking_id', 'BN-FLIGHT-123')->count());
    }

    public function test_get_order_returns_unified_bundle_structure(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedBooknowWallet();

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/orders/sync-bundle', $this->bundlePayload())->assertCreated();

        $orderId = (int) Order::query()->value('id');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.is_bundle', true)
            ->assertJsonPath('data.product_type', 'flight')
            ->assertJsonPath('data.flight.pnr', 'ABC123')
            ->assertJsonPath('data.esim.qr', 'LPA:1$example.com$BUNDLE')
            ->assertJsonPath('data.insurance.report_reference', 'ENC789')
            ->assertJsonPath('data.metadata.bundle', true)
            ->assertJsonPath('data.metadata.booknow_insurance_order_id', '12');

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.orders.0.is_bundle', true)
            ->assertJsonPath('data.orders.0.items.2.type', 'insurance');
    }

    public function test_sync_bundle_requires_flight_item_and_insurance_order_id(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $payload = $this->bundlePayload();
        $payload['items'] = array_values(array_filter(
            $payload['items'],
            static fn (array $item): bool => $item['type'] !== 'flight',
        ));

        $this->postJson('/api/v1/orders/sync-bundle', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $payload = $this->bundlePayload();
        unset($payload['items'][2]['item_details']['order_id']);

        $this->postJson('/api/v1/orders/sync-bundle', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.2.item_details.order_id']);
    }

    private function seedBooknowWallet(): void
    {
        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => Provider::KEY_BOOKNOW,
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
    private function bundlePayload(): array
    {
        return [
            'source' => 'mobile_app',
            'product_type' => 'flight',
            'status' => 'confirmed',
            'currency' => 'LYD',
            'grand_total' => 850.0,
            'provider_booking' => [
                'booking_id' => 'BN-FLIGHT-123',
                'provider' => 'booknow',
                'pnr' => 'ABC123',
            ],
            'contact' => [
                'name' => 'Ahmed Ali',
                'email' => 'ahmed@example.com',
                'phone' => '+218900000000',
            ],
            'passengers' => [
                [
                    'type' => 'adult',
                    'first_name' => 'Ahmed',
                    'last_name' => 'Ali',
                ],
            ],
            'items' => [
                [
                    'type' => 'flight',
                    'product_type' => 'ticket',
                    'title' => 'TIP → MJI',
                    'unit_price' => 600.0,
                    'item_details' => [
                        'item_id' => '11',
                        'seats' => ['0' => ['0' => '12A']],
                        'pnr' => 'ABC123',
                    ],
                ],
                [
                    'type' => 'esim',
                    'product_type' => 'esim',
                    'title' => 'Turkey 3GB / 7 Days',
                    'unit_price' => 100.0,
                    'item_details' => [
                        'item_id' => 'esim-item-1',
                        'booking_uuid' => 'esim-booking-uuid-1',
                        'iccid' => '8901234567890123456',
                        'qr' => 'LPA:1$example.com$BUNDLE',
                    ],
                ],
                [
                    'type' => 'insurance',
                    'product_type' => 'insurance',
                    'product_subtype' => 'travel',
                    'title' => 'Travel Insurance · 7 Days',
                    'unit_price' => 150.0,
                    'item_details' => [
                        'item_id' => '34',
                        'order_id' => '12',
                        'ticket_number' => 'CMP-58737',
                        'report_reference' => 'ENC789',
                        'duration_id' => 1,
                        'zone_id' => 1,
                        'policy_date_from' => '2026-08-01',
                        'policy_date_to' => '2026-08-07',
                    ],
                ],
            ],
            'payment' => [
                'method' => 'card',
                'amount' => 850.0,
                'currency' => 'LYD',
                'transaction_id' => 'txn_bundle_001',
            ],
            'metadata' => [
                'bundle' => true,
                'booknow_flight_order_id' => 'BN-FLIGHT-123',
                'booknow_insurance_order_id' => '12',
                'booknow_esim_booking_ids' => ['esim-booking-uuid-1'],
            ],
        ];
    }
}
