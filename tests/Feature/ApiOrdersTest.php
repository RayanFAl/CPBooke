<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiOrdersTest extends TestCase
{
    use RefreshDatabase;

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
}