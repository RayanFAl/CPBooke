<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\TravelSearchIntent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminManualBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_a_manual_flight_booking_for_a_customer(): void
    {
        $admin = $this->adminUser();
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        TravelSearchIntent::query()->create([
            'user_id' => $customer->id,
            'origin' => 'MJI',
            'destination' => 'IST',
            'route_key' => TravelSearchIntent::routeKeyFor('MJI', 'IST', '2026-09-10'),
            'departure_date' => '2026-09-10',
            'last_seen_price' => '890.00',
            'currency' => 'LYD',
            'last_searched_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.create', ['customer_id' => $customer->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/pages/Create', false)
                ->where('selected_customer_id', $customer->id)
            );

        $this->actingAs($admin)
            ->post(route('admin.orders.store'), [
                'customer_id' => $customer->id,
                'service_type' => Order::SERVICE_TYPE_FLIGHT,
                'booking_reference' => 'PNR123',
                'provider_name' => 'BookNow',
                'currency' => 'LYD',
                'total_amount' => '890.00',
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'payment_method' => Order::PAYMENT_METHOD_CASH,
                'passenger_name' => 'Ahmad Ali',
                'origin' => 'MJI',
                'destination' => 'IST',
                'departure_date' => '2026-09-10',
                'internal_notes' => 'Phone booking',
            ])
            ->assertRedirect();

        $order = Order::query()->where('booking_reference', 'PNR123')->firstOrFail();

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(Order::SOURCE_ADMIN_MANUAL, $order->source);
        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->payment_status);
        $this->assertNotNull($order->customer->travelSearchIntents()->whereNotNull('converted_at')->first());
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        return $admin;
    }
}
