<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_manager_can_apply_a_valid_status_transition(): void
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
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['airline' => 'Saudia', 'pnr' => 'OPS001'],
            'currency' => 'USD',
            'total_amount' => 145.00,
            'request_payload' => ['airline' => 'Saudia', 'pnr' => 'OPS001'],
        ]);

        $this->actingAs($actor)
            ->get('/admin/orders')
            ->assertOk();

        $this->actingAs($actor)
            ->put("/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_REFUNDED,
            ])
            ->assertRedirect(route('admin.orders.show', $order, absolute: false));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_REFUNDED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'user_id' => $actor->id,
            'field' => 'status',
            'old_value' => Order::STATUS_CONFIRMED,
            'new_value' => Order::STATUS_REFUNDED,
        ]);
    }

    public function test_operations_manager_can_update_payment_status_independently(): void
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
            'provider_name' => 'Provider Payment',
            'booking_reference' => 'BK-PAY-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['airline' => 'Saudia'],
            'currency' => 'USD',
            'total_amount' => 100.00,
            'request_payload' => ['airline' => 'Saudia'],
        ]);

        $this->actingAs($actor)
            ->put("/admin/orders/{$order->id}/payment-status", [
                'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            ])
            ->assertRedirect(route('admin.orders.show', $order, absolute: false));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ]);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'user_id' => $actor->id,
            'field' => 'payment_status',
            'old_value' => Order::PAYMENT_STATUS_UNPAID,
            'new_value' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ]);

        $this->assertDatabaseHas('financial_transactions', [
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_REFUND,
            'amount' => '100.00',
            'currency' => 'USD',
            'source' => FinancialTransaction::SOURCE_PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ]);

        $this->assertSame(1, FinancialTransaction::query()->where('order_id', $order->id)->count());
    }

    public function test_retrying_the_same_payment_status_change_does_not_create_a_second_transaction(): void
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
            'provider_name' => 'Provider Same Payment',
            'booking_reference' => 'BK-PAY-SAME-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Same Payment Hotel'],
            'currency' => 'USD',
            'total_amount' => 180.00,
            'request_payload' => ['hotel_name' => 'Same Payment Hotel'],
        ]);

        $this->actingAs($actor)
            ->put("/admin/orders/{$order->id}/payment-status", [
                'payment_status' => Order::PAYMENT_STATUS_PAID,
            ])
            ->assertRedirect(route('admin.orders.show', $order, absolute: false));

        $this->actingAs($actor)
            ->put("/admin/orders/{$order->id}/payment-status", [
                'payment_status' => Order::PAYMENT_STATUS_PAID,
            ])
            ->assertRedirect(route('admin.orders.show', $order, absolute: false));

        $this->assertDatabaseHas('financial_transactions', [
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '180.00',
            'currency' => 'USD',
            'source' => FinancialTransaction::SOURCE_PAYMENT_STATUS_PAID,
        ]);

        $this->assertSame(1, FinancialTransaction::query()->where('order_id', $order->id)->count());
    }

    public function test_paid_payment_status_creates_a_single_payment_transaction(): void
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
            'provider_name' => 'Provider Paid',
            'booking_reference' => 'BK-PAY-PAID-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['airline' => 'Saudia'],
            'currency' => 'USD',
            'total_amount' => 155.00,
            'request_payload' => ['airline' => 'Saudia'],
        ]);

        $this->actingAs($actor)
            ->put("/admin/orders/{$order->id}/payment-status", [
                'payment_status' => Order::PAYMENT_STATUS_PAID,
            ])
            ->assertRedirect(route('admin.orders.show', $order, absolute: false));

        $this->assertDatabaseHas('financial_transactions', [
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '155.00',
            'currency' => 'USD',
            'source' => FinancialTransaction::SOURCE_PAYMENT_STATUS_PAID,
        ]);

        $this->assertSame(1, FinancialTransaction::query()->where('order_id', $order->id)->count());
    }

    public function test_invalid_status_transition_is_rejected(): void
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
            'provider_name' => 'Provider Draft',
            'booking_reference' => 'BK-DRAFT-001',
            'status' => Order::STATUS_DRAFT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Draft Hotel'],
            'currency' => 'USD',
            'total_amount' => 320.00,
            'request_payload' => ['hotel_name' => 'Draft Hotel'],
        ]);

        $this->actingAs($actor)
            ->from(route('admin.orders.show', $order, absolute: false))
            ->put("/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_COMPLETED,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_DRAFT,
        ]);
    }

    public function test_operations_manager_can_update_internal_notes(): void
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
            'provider_name' => 'Provider Notes',
            'booking_reference' => 'BK-NOTES-001',
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_INSURANCE,
            'details' => ['insurance_type' => 'premium'],
            'currency' => 'USD',
            'total_amount' => 210.00,
            'request_payload' => ['insurance_type' => 'premium'],
        ]);

        $this->actingAs($actor)
            ->put("/admin/orders/{$order->id}/notes", [
                'internal_notes' => 'Escalated to provider operations desk.',
            ])
            ->assertRedirect(route('admin.orders.show', $order, absolute: false));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'internal_notes' => 'Escalated to provider operations desk.',
        ]);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'user_id' => $actor->id,
            'field' => 'internal_notes',
            'new_value' => 'Escalated to provider operations desk.',
        ]);
    }

    public function test_history_and_admin_actions_respect_permissions(): void
    {
        $operationsManager = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $financeManager = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $operationsManager->refresh()->syncRolesByName(['operations_manager']);
        $financeManager->refresh()->syncRolesByName(['finance_manager']);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Provider History',
            'booking_reference' => 'BK-HISTORY-001',
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['airline' => 'Riyadh Air'],
            'currency' => 'USD',
            'total_amount' => 210.00,
            'internal_notes' => 'Initial note',
            'request_payload' => ['airline' => 'Riyadh Air'],
        ]);

        $this->actingAs($operationsManager)
            ->put("/admin/orders/{$order->id}/notes", [
                'internal_notes' => 'Updated note',
            ])
            ->assertRedirect(route('admin.orders.show', $order, absolute: false));

        $this->actingAs($operationsManager)
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/pages/Show', false)
                ->where('order.internal_notes', 'Updated note')
                ->has('order.histories', 1)
                ->where('order.histories.0.field', 'internal_notes')
                ->where('order.histories.0.user.email', $operationsManager->email)
            );

        $this->actingAs($financeManager)
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/pages/Show', false)
                ->where('order.internal_notes', 'Updated note')
                ->where('order.histories', [])
            );

        $this->actingAs($financeManager)
            ->put("/admin/orders/{$order->id}/status", [
                'status' => Order::STATUS_CONFIRMED,
            ])
            ->assertForbidden();

        $this->actingAs($financeManager)
            ->put("/admin/orders/{$order->id}/payment-status", [
                'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
            ])
            ->assertForbidden();

        $this->actingAs($financeManager)
            ->put("/admin/orders/{$order->id}/notes", [
                'internal_notes' => 'Finance cannot edit this.',
            ])
            ->assertForbidden();
    }

    public function test_admin_order_show_includes_financial_transactions_in_latest_first_order(): void
    {
        $financeManager = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $financeManager->refresh()->syncRolesByName(['finance_manager']);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Provider Timeline',
            'booking_reference' => 'BK-TIMELINE-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['airline' => 'Saudia'],
            'currency' => 'USD',
            'total_amount' => 240.00,
            'request_payload' => ['airline' => 'Saudia'],
        ]);

        $olderTransaction = FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '240.00',
            'currency' => 'USD',
            'source' => FinancialTransaction::SOURCE_ORDER_CREATION,
        ]);

        $olderTransaction->forceFill([
            'created_at' => Carbon::parse('2026-05-07 09:00:00'),
            'updated_at' => Carbon::parse('2026-05-07 09:00:00'),
        ])->save();

        $latestTransaction = FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_REFUND,
            'amount' => '75.00',
            'currency' => 'USD',
            'source' => FinancialTransaction::SOURCE_PAYMENT_STATUS_REFUNDED,
        ]);

        $latestTransaction->forceFill([
            'created_at' => Carbon::parse('2026-05-07 11:00:00'),
            'updated_at' => Carbon::parse('2026-05-07 11:00:00'),
        ])->save();

        $this->actingAs($financeManager)
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/pages/Show', false)
                ->has('order.transactions', 2)
                ->where('order.financial_insight.net_amount', '165.00')
                ->where('order.financial_insight.currency', 'USD')
                ->where('order.transactions.0.type', FinancialTransaction::TYPE_REFUND)
                ->where('order.transactions.0.source', FinancialTransaction::SOURCE_PAYMENT_STATUS_REFUNDED)
                ->where('order.transactions.1.type', FinancialTransaction::TYPE_PAYMENT)
                ->where('order.transactions.1.source', FinancialTransaction::SOURCE_ORDER_CREATION)
            );
    }

    public function test_admin_order_show_includes_structured_ticket_payload(): void
    {
        $operationsManager = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $operationsManager->refresh()->syncRolesByName(['operations_manager']);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Buraq Air',
            'external_booking_id' => '01ktgspkyr6ma0fjqjc7vexyry',
            'booking_reference' => 'CP0001BA',
            'status' => Order::STATUS_TICKETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => [
                'pnr' => 'AAXKDO',
                'airline' => 'Buraq Air',
                'origin' => 'MJI',
                'destination' => 'TUN',
                'departure_time' => '2026-06-20 10:25:00',
                'provider_order_number' => 'WFQ0001OZ',
                'segments' => [
                    [
                        'flight_number' => 'BM0400',
                        'departure_airport' => 'MJI',
                        'arrival_airport' => 'TUN',
                        'departure_time' => '2026-06-20 10:25:00',
                        'arrival_time' => '2026-06-20 10:35:00',
                        'action_code' => 'HK',
                        'cabin_type' => 'Y',
                        'class' => 'Y',
                        'etkt' => '532 2300824806/01',
                    ],
                ],
            ],
            'currency' => 'LYD',
            'total_amount' => 590.00,
            'request_payload' => [
                'passengers' => [
                    [
                        'type' => 'adult',
                        'first_name' => 'RAYAN',
                        'last_name' => 'FATHI',
                        'passport_number' => 'AB1234567',
                    ],
                ],
                'contact' => [
                    'first_name' => 'RAYAN',
                    'last_name' => 'FATHI',
                    'email' => 'a.rayan@median.ly',
                    'phone' => '+218943215277',
                ],
            ],
            'response_payload' => [
                'provider_order_number' => 'WFQ0001OZ',
                'status' => 'ticketed',
                'passengers' => [
                    [
                        'type' => 'adult',
                        'first_name' => 'RAYAN',
                        'last_name' => 'FATHI',
                        'passport_number' => 'AB1234567',
                    ],
                ],
                'contact' => [
                    'first_name' => 'RAYAN',
                    'last_name' => 'FATHI',
                    'email' => 'a.rayan@median.ly',
                    'phone' => '+218943215277',
                ],
            ],
        ]);

        $this->actingAs($operationsManager)
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/pages/Show', false)
                ->where('order.ticket.pnr', 'AAXKDO')
                ->where('order.ticket.origin', null)
                ->where('order.ticket.destination', null)
                ->where('order.ticket.provider_order_number', 'WFQ0001OZ')
                ->has('order.ticket.passengers', 1)
                ->has('order.ticket.segments', 1)
                ->where('order.ticket.contact.email', 'a.rayan@median.ly')
                ->where('order.ticket.contact.first_name', 'RAYAN')
                ->where('order.ticket.contact.last_name', 'FATHI')
                ->has('order.ticket.segment_coupons', 1)
                ->where('order.ticket.segment_coupons.0.flight_number', 'BM0400')
                ->where('order.ticket.segment_coupons.0.passenger_name', 'RAYAN FATHI')
                ->where('order.ticket.segment_coupons.0.etkt', '532 2300824806/01')
                ->where('order.ticket.segment_coupons.0.status_code', 'HK')
                ->where('order.ticket.segment_coupons.0.cabin_type', 'Y')
                ->where('order.ticket.segment_coupons.0.booking_class', 'Y')
                ->where('order.ticket.items', [])
                ->missing('order.ticket.order_context.external_booking_id')
                ->missing('order.ticket.order_context.id')
            );
    }

    public function test_operations_manager_can_search_orders_by_reference_customer_or_provider(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $matchingCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'name' => 'Searchable Customer',
            'email' => 'searchable@example.com',
        ]);

        $otherCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'name' => 'Other Customer',
            'email' => 'other@example.com',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['operations_manager']);

        $matchingOrder = Order::query()->create([
            'customer_id' => $matchingCustomer->id,
            'provider_name' => 'Unique Provider',
            'booking_reference' => 'CP-SEARCH-001',
            'external_booking_id' => 'EXT-SEARCH-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 100.00,
            'request_payload' => [],
            'details' => [
                'pnr' => 'AD2DWM',
                'airline' => 'Buraq Air',
                'airline_code' => 'BM',
                'provider_order_number' => 'WFQ0001OZ',
                'segments' => [
                    [
                        'departure_airport' => 'MJI',
                        'arrival_airport' => 'TUN',
                        'departure_time' => '2026-10-05 00:00:00',
                    ],
                ],
            ],
        ]);

        Order::query()->create([
            'customer_id' => $otherCustomer->id,
            'provider_name' => 'Another Provider',
            'booking_reference' => 'CP-OTHER-999',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 50.00,
            'request_payload' => [],
        ]);

        $this->actingAs($actor)
            ->get('/admin/orders?search=CP-SEARCH-001')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/pages/Index', false)
                ->where('filters.search', 'CP-SEARCH-001')
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $matchingOrder->id)
                ->where('orders.data.0.flight.pnr', 'AD2DWM')
                ->where('orders.data.0.flight.origin', 'MJI')
                ->where('orders.data.0.flight.destination', 'TUN')
                ->where('orders.data.0.flight.airline_code', 'BM')
                ->where('orders.data.0.ticket_number', 'WFQ0001OZ')
            );

        $this->actingAs($actor)
            ->get('/admin/orders?search=AD2DWM')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $matchingOrder->id)
            );

        $this->actingAs($actor)
            ->get('/admin/orders?search=searchable@example.com')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $matchingOrder->id)
            );

        $this->actingAs($actor)
            ->get('/admin/orders?search=Unique Provider')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $matchingOrder->id)
            );
    }
}