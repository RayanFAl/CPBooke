<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiInsuranceOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_insurance_creates_order_and_returns_cpbooke_ids(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedInsuranceProviderWallet();

        Sanctum::actingAs($customer);

        $payload = $this->insurancePayload();

        $response = $this->postJson('/api/v1/orders/sync-insurance', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Order saved successfully.')
            ->assertJsonPath('data.id', '12')
            ->assertJsonPath('data.product_type', 'insurance')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.cpbooke_id', 1)
            ->assertJsonPath('data.insurance.item_id', '34')
            ->assertJsonPath('data.insurance.ticket_number', 'CMP-58737')
            ->assertJsonPath('data.insurance.report_reference', 'ENC789')
            ->assertJsonPath('data.insurance.zone_name', 'Europe')
            ->assertJsonPath('meta.created', true)
            ->assertJsonPath('meta.idempotent', false);

        $orderNumber = (string) $response->json('data.number');
        $this->assertNotSame('', $orderNumber);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'external_booking_id' => '12',
            'booking_reference' => $orderNumber,
            'service_type' => Order::SERVICE_TYPE_INSURANCE,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'total_amount' => '150.00',
        ]);

        $this->assertDatabaseHas('providers', [
            'key' => Provider::KEY_BOOKNOW_INSURANCE,
        ]);
    }

    public function test_sync_insurance_is_idempotent_by_booking_id(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedInsuranceProviderWallet();

        Sanctum::actingAs($customer);

        $payload = $this->insurancePayload();

        $this->postJson('/api/v1/orders/sync-insurance', $payload)->assertCreated();

        $payload['grand_total'] = 175.00;
        $payload['items'][0]['unit_price'] = 175.00;

        $this->postJson('/api/v1/orders/sync-insurance', $payload)
            ->assertOk()
            ->assertJsonPath('data.grand_total', '175.00')
            ->assertJsonPath('meta.idempotent', true);

        $this->assertSame(1, Order::query()->where('external_booking_id', '12')->count());
    }

    public function test_get_orders_includes_insurance_product_type_and_details(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedInsuranceProviderWallet();

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/orders/sync-insurance', $this->insurancePayload())->assertCreated();

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.orders.0.product_type', 'insurance')
            ->assertJsonPath('data.orders.0.service_type', 'insurance')
            ->assertJsonPath('data.orders.0.insurance.item_id', '34')
            ->assertJsonPath('data.orders.0.insurance.ticket_number', 'CMP-58737');

        $orderId = (int) Order::query()->value('id');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.product_type', 'insurance')
            ->assertJsonPath('data.insurance.report_reference', 'ENC789')
            ->assertJsonPath('data.insurance.policy_date_from', '2026-08-01')
            ->assertJsonPath('data.items.0.title', 'Travel Insurance · 7 Days')
            ->assertJsonPath('data.metadata.related_flight_order_id', 123);
    }

    public function test_sync_insurance_requires_item_id(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $payload = $this->insurancePayload();
        unset($payload['items'][0]['item_details']['item_id']);

        $this->postJson('/api/v1/orders/sync-insurance', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.item_details.item_id']);
    }

    private function seedInsuranceProviderWallet(): void
    {
        $provider = Provider::query()->create([
            'name' => 'BookNow Insurance',
            'key' => Provider::KEY_BOOKNOW_INSURANCE,
            'status' => Provider::STATUS_ACTIVE,
        ]);

        ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => ProviderWallet::ENVIRONMENT_PRODUCTION,
            'balance' => '1000.00',
            'allow_negative' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function insurancePayload(): array
    {
        return [
            'source' => 'mobile_app',
            'product_type' => 'insurance',
            'status' => 'confirmed',
            'currency' => 'LYD',
            'grand_total' => 150.0,
            'contact' => [
                'name' => 'Ahmed Ali',
                'email' => 'ahmed@example.com',
                'phone' => '+218900000000',
            ],
            'provider_booking' => [
                'booking_id' => '12',
                'provider' => 'booknow_insurance',
                'order_id' => '12',
                'order_number' => 'ORD-2026-0002',
            ],
            'items' => [
                [
                    'type' => 'insurance',
                    'product_type' => 'insurance',
                    'product_subtype' => 'travel',
                    'title' => 'Travel Insurance · 7 Days',
                    'quantity' => 1,
                    'unit_price' => 150.0,
                    'item_details' => [
                        'item_id' => '34',
                        'provider' => 'albaraka',
                        'ticket_number' => 'CMP-58737',
                        'report_reference' => 'ENC789',
                        'zone_id' => 1,
                        'zone_name' => 'Europe',
                        'duration_id' => 1,
                        'duration_label' => '7 Days',
                        'policy_date_from' => '2026-08-01',
                        'policy_date_to' => '2026-08-07',
                    ],
                ],
            ],
            'payment' => [
                'method' => 'card',
                'amount' => 150.0,
                'currency' => 'LYD',
                'transaction_id' => 'ins_txn_001',
            ],
            'metadata' => [
                'source_screen' => 'mobile_app',
                'booknow_insurance_item_id' => '34',
                'related_flight_order_id' => 123,
                'related_flight_booking_id' => 'BN-FLIGHT-ID',
            ],
        ];
    }
}
