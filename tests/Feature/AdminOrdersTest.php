<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_manager_can_view_orders_and_update_status(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['operations_manager']);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Provider One',
            'booking_reference' => 'BK-OPS-001',
            'status' => Order::STATUS_PENDING,
            'currency' => 'USD',
            'total_amount' => 145.00,
            'request_payload' => ['room' => 'suite'],
        ]);

        $this->actingAs($actor)
            ->get('/admin/orders')
            ->assertOk();

        $this->actingAs($actor)
            ->put("/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_CANCELLED,
            ])
            ->assertRedirect(route('admin.orders.show', $order, absolute: false));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_CANCELLED,
        ]);
    }

    public function test_finance_manager_can_view_orders_but_cannot_update_status(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['finance_manager']);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Provider Finance',
            'booking_reference' => 'BK-FIN-001',
            'status' => Order::STATUS_CONFIRMED,
            'currency' => 'USD',
            'total_amount' => 320.00,
            'request_payload' => [],
        ]);

        $this->actingAs($actor)
            ->get("/admin/orders/{$order->id}")
            ->assertOk();

        $this->actingAs($actor)
            ->put("/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_CANCELLED,
            ])
            ->assertForbidden();
    }

    public function test_super_admin_has_full_access(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['super_admin']);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Provider Super',
            'booking_reference' => 'BK-SUPER-001',
            'status' => Order::STATUS_PENDING,
            'currency' => 'USD',
            'total_amount' => 210.00,
            'request_payload' => ['channel' => 'web'],
        ]);

        $this->actingAs($actor)
            ->get('/admin/orders')
            ->assertOk();

        $this->actingAs($actor)
            ->get("/admin/orders/{$order->id}")
            ->assertOk();

        $this->actingAs($actor)
            ->put("/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_CONFIRMED,
            ])
            ->assertRedirect(route('admin.orders.show', $order, absolute: false));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_CONFIRMED,
        ]);
    }
}