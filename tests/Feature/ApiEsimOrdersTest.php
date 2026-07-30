<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiEsimOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_esim_creates_order_and_returns_cpbooke_ids(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedEsimProviderWallet();

        Sanctum::actingAs($customer);

        $payload = $this->esimPayload();

        $response = $this->postJson('/api/v1/orders/sync-esim', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Order saved successfully.')
            ->assertJsonPath('data.id', 'esim-booking-uuid-001')
            ->assertJsonPath('data.product_type', 'esim')
            ->assertJsonPath('data.cpbooke_id', 1)
            ->assertJsonPath('data.esim.country', 'TN')
            ->assertJsonPath('data.esim.qr', 'LPA:1$example.com$ACTIVATION')
            ->assertJsonPath('meta.created', true)
            ->assertJsonPath('meta.idempotent', false);

        $orderNumber = (string) $response->json('data.number');
        $this->assertNotSame('', $orderNumber);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'external_booking_id' => 'esim-booking-uuid-001',
            'booking_reference' => $orderNumber,
            'service_type' => Order::SERVICE_TYPE_ESIM,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'total_amount' => '12.50',
        ]);

        $this->assertDatabaseHas('providers', [
            'key' => Provider::KEY_BOOKNOW_ESIM,
        ]);
    }

    public function test_sync_esim_is_idempotent_by_booking_id(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedEsimProviderWallet();

        Sanctum::actingAs($customer);

        $payload = $this->esimPayload();

        $this->postJson('/api/v1/orders/sync-esim', $payload)->assertCreated();

        $payload['grand_total'] = 15.00;
        $payload['items'][0]['unit_price'] = 15.00;

        $this->postJson('/api/v1/orders/sync-esim', $payload)
            ->assertOk()
            ->assertJsonPath('data.grand_total', '15.00')
            ->assertJsonPath('meta.idempotent', true);

        $this->assertSame(1, Order::query()->where('external_booking_id', 'esim-booking-uuid-001')->count());
    }

    public function test_get_orders_includes_esim_product_type_and_details(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seedEsimProviderWallet();

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/orders/sync-esim', $this->esimPayload())->assertCreated();

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.orders.0.product_type', 'esim')
            ->assertJsonPath('data.orders.0.service_type', 'esim')
            ->assertJsonPath('data.orders.0.esim.country', 'TN')
            ->assertJsonPath('data.orders.0.esim.qr', 'LPA:1$example.com$ACTIVATION');

        $orderId = (int) Order::query()->value('id');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.product_type', 'esim')
            ->assertJsonPath('data.esim.iccid', '8901234567890123456')
            ->assertJsonPath('data.esim.activation_code', 'ACTIVATION-CODE-001')
            ->assertJsonPath('data.items.0.title', 'Tunisia 1GB 30 Days');
    }

    private function seedEsimProviderWallet(): void
    {
        $provider = Provider::query()->create([
            'name' => 'BookNow eSIM',
            'key' => Provider::KEY_BOOKNOW_ESIM,
            'status' => Provider::STATUS_ACTIVE,
        ]);

        ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'USD',
            'environment' => ProviderWallet::ENVIRONMENT_PRODUCTION,
            'balance' => '1000.00',
            'allow_negative' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function esimPayload(): array
    {
        return [
            'source' => 'mobile_app',
            'product_type' => 'esim',
            'status' => 'confirmed',
            'currency' => 'USD',
            'grand_total' => 12.5,
            'contact' => [
                'name' => 'Rayan Fathi',
                'email' => 'a.rayan@median.ly',
            ],
            'provider_booking' => [
                'booking_id' => 'esim-booking-uuid-001',
                'provider' => 'booknow_esim',
                'order_id' => 'BN-ESIM-7788',
            ],
            'items' => [
                [
                    'type' => 'esim',
                    'title' => 'Tunisia 1GB 30 Days',
                    'quantity' => 1,
                    'unit_price' => 12.5,
                    'item_details' => [
                        'country' => 'TN',
                        'data' => '1GB',
                        'validity_days' => 30,
                        'iccid' => '8901234567890123456',
                        'activation_code' => 'ACTIVATION-CODE-001',
                        'qr' => 'LPA:1$example.com$ACTIVATION',
                    ],
                ],
            ],
            'payment' => [
                'method' => 'wallet',
                'status' => 'paid',
                'amount' => 12.5,
                'currency' => 'USD',
            ],
            'metadata' => [
                'related_flight_booking_id' => null,
            ],
        ];
    }
}
