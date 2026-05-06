<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_a_confirmed_order(): void
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
            'request_payload' => [
                'trip_type' => 'hotel',
                'check_in' => '2026-06-01',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.status', Order::STATUS_CONFIRMED);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'provider_name' => 'Booke Provider',
            'status' => Order::STATUS_CONFIRMED,
        ]);
    }

    public function test_customer_can_create_a_failed_order_when_provider_fails(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/orders', [
            'provider_name' => 'Failing Provider',
            'currency' => 'USD',
            'total_amount' => 80.00,
            'request_payload' => [
                'simulate_failure' => true,
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.order.status', Order::STATUS_FAILED);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'status' => Order::STATUS_FAILED,
        ]);
    }

    public function test_customer_only_sees_their_own_orders(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $otherCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Primary Provider',
            'booking_reference' => 'BK-SELF-001',
            'status' => Order::STATUS_CONFIRMED,
            'currency' => 'USD',
            'total_amount' => 50.00,
            'request_payload' => [],
        ]);

        $foreignOrder = Order::query()->create([
            'customer_id' => $otherCustomer->id,
            'provider_name' => 'Foreign Provider',
            'booking_reference' => 'BK-OTHER-001',
            'status' => Order::STATUS_PENDING,
            'currency' => 'USD',
            'total_amount' => 90.00,
            'request_payload' => [],
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.booking_reference', 'BK-SELF-001');

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
            'request_payload' => ['product' => 'blocked'],
        ])->assertForbidden();

        $this->getJson('/api/v1/orders')->assertForbidden();
    }
}