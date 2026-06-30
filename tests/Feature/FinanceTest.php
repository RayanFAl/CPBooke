<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_page_does_not_fail_when_transactions_table_is_missing(): void
    {
        $financeActor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $financeActor->refresh()->syncRolesByName(['finance_manager']);

        Schema::drop('financial_transactions');

        $this->actingAs($financeActor)
            ->get('/admin/finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/finance/pages/Index', false)
                ->where('metrics.transactions_count', 0)
                ->where('metrics.payments_total', '0.00')
                ->where('metrics.refunds_total', '0.00')
                ->has('transactions', 0)
            );
    }

    public function test_finance_page_does_not_fail_when_transaction_status_column_is_missing(): void
    {
        $financeActor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $financeActor->refresh()->syncRolesByName(['finance_manager']);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Fallback Finance Provider',
            'booking_reference' => 'BK-FIN-FALLBACK-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Fallback Hotel'],
            'currency' => 'USD',
            'total_amount' => 150.00,
            'request_payload' => ['hotel_name' => 'Fallback Hotel'],
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '150.00',
            'currency' => 'USD',
            'source' => 'fallback_source',
        ]);

        Schema::table('financial_transactions', function ($table): void {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn('status');
        });

        $this->actingAs($financeActor)
            ->get('/admin/finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/finance/pages/Index', false)
                ->where('dashboard.counts.transactions', 1)
                ->where('transactions.0.order_id', $order->id)
                ->where('transactions.0.type', FinancialTransaction::TYPE_PAYMENT)
                ->where('transactions.0.source', 'fallback_source')
            );
    }

    public function test_finance_page_does_not_fail_when_ledger_entries_table_is_missing(): void
    {
        $financeActor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $financeActor->refresh()->syncRolesByName(['finance_manager']);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Missing Ledger Provider',
            'booking_reference' => 'BK-FIN-LEDGER-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Missing Ledger Hotel'],
            'currency' => 'USD',
            'total_amount' => 175.00,
            'request_payload' => ['hotel_name' => 'Missing Ledger Hotel'],
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '175.00',
            'currency' => 'USD',
            'source' => 'missing_ledger_table_source',
        ]);

        Schema::drop('financial_ledger_entries');

        $this->actingAs($financeActor)
            ->get('/admin/finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/finance/pages/Index', false)
                ->where('dashboard.counts.transactions', 1)
                ->where('dashboard.reconciliation.counts.transactions_missing_ledger', 0)
                ->where('dashboard.reconciliation.counts.transactions_unbalanced', 0)
                ->where('transactions.0.order_id', $order->id)
                ->where('transactions.0.source', 'missing_ledger_table_source')
            );
    }

    public function test_new_api_order_creates_a_transaction_visible_in_finance_page(): void
    {
        $financeActor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $financeActor->refresh()->syncRolesByName(['finance_manager']);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/orders', [
            'provider_name' => 'Finance Linked Provider',
            'currency' => 'usd',
            'total_amount' => 210.50,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => [
                'passenger_name' => 'Finance User',
                'airline' => 'Booke Air',
                'pnr' => 'FIN21050',
            ],
        ])->assertCreated();

        $orderId = (int) $response->json('data.order.id');

        $transaction = FinancialTransaction::query()->where('order_id', $orderId)->first();

        $this->assertNotNull($transaction);
        $this->assertSame(FinancialTransaction::TYPE_PAYMENT, $transaction->type);
        $this->assertSame('210.50', $transaction->amount);
        $this->assertSame(Order::DEFAULT_CURRENCY, $transaction->currency);
        $this->assertSame('order_creation', $transaction->source);

        $this->actingAs($financeActor)
            ->get('/admin/finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/finance/pages/Index', false)
                ->where('transactions.0.order_id', $orderId)
                ->where('transactions.0.type', FinancialTransaction::TYPE_PAYMENT)
                ->where('transactions.0.source', 'order_creation')
                ->where('transactions.0.amount', '210.50')
            );
    }

    public function test_finance_page_shows_latest_transactions_in_descending_order(): void
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
            'provider_name' => 'Finance Provider',
            'booking_reference' => 'BK-FIN-100',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Finance Hotel'],
            'currency' => 'USD',
            'total_amount' => 300.00,
            'request_payload' => ['hotel_name' => 'Finance Hotel'],
        ]);

        foreach (range(1, 21) as $index) {
            $transaction = FinancialTransaction::query()->create([
                'order_id' => $order->id,
                'type' => $index % 2 === 0 ? FinancialTransaction::TYPE_REFUND : FinancialTransaction::TYPE_PAYMENT,
                'amount' => (string) (100 + $index),
                'currency' => 'USD',
                'source' => sprintf('source_%02d', $index),
            ]);

            $transaction->forceFill([
                'created_at' => Carbon::parse('2026-05-07 10:00:00')->addMinutes($index),
                'updated_at' => Carbon::parse('2026-05-07 10:00:00')->addMinutes($index),
            ])->save();
        }

        $this->actingAs($actor)
            ->get('/admin/finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/finance/pages/Index', false)
                ->where('dashboard.counts.transactions', 21)
                ->where('dashboard.totals.payments', '1221.00')
                ->where('dashboard.totals.refunds', '1110.00')
                ->where('dashboard.totals.net', '111.00')
                ->has('dashboard.analytics')
                ->has('dashboard.analytics.time_series')
                ->has('dashboard.analytics.segmentation')
                ->has('dashboard.analytics.insights')
                ->where('dashboard.analytics.insights.latest_event.type', FinancialTransaction::TYPE_PAYMENT)
                ->where('dashboard.analytics.insights.latest_event.source', 'source_21')
                ->where('dashboard.analytics.insights.latest_event.order_id', $order->id)
                ->has('dashboard.analytics.time_series.labels', 7)
                ->has('dashboard.analytics.time_series.datasets', 3)
                ->has('dashboard.activity.latest_transactions', 20)
                ->has('transactions', 20)
                ->where('transactions.0.source', 'source_21')
                ->where('transactions.0.order_id', $order->id)
                ->where('dashboard.activity.latest_transactions.0.source', 'source_21')
                ->where('transactions.1.source', 'source_20')
                ->where('transactions.19.source', 'source_02')
            );
    }

    public function test_finance_page_shows_total_payments_refunds_and_net_total(): void
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
            'provider_name' => 'Finance Summary Provider',
            'booking_reference' => 'BK-FIN-SUM-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Finance Summary Hotel'],
            'currency' => 'USD',
            'total_amount' => 400.00,
            'request_payload' => ['hotel_name' => 'Finance Summary Hotel'],
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '400.00',
            'currency' => 'USD',
            'source' => 'summary_payment',
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_REFUND,
            'amount' => '125.50',
            'currency' => 'USD',
            'source' => 'summary_refund',
        ]);

        $this->actingAs($actor)
            ->get('/admin/finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/finance/pages/Index', false)
                ->where('metrics.payments_total', '400.00')
                ->where('metrics.refunds_total', '125.50')
                ->where('metrics.net_total', '274.50')
                ->has('ledger_preview', 2)
                ->where('ledger_preview.0.type', FinancialTransaction::TYPE_REFUND)
                ->where('ledger_preview.0.debit_account', FinancialTransaction::ACCOUNT_CUSTOMER_LIABILITY)
                ->where('ledger_preview.0.credit_account', FinancialTransaction::ACCOUNT_CASH)
                ->where('ledger_preview.0.reference_type', FinancialTransaction::REFERENCE_TYPE_ORDER)
                ->where('ledger_preview.0.reference_id', $order->id)
                ->where('ledger_preview.1.type', FinancialTransaction::TYPE_PAYMENT)
                ->where('ledger_preview.1.debit_account', FinancialTransaction::ACCOUNT_CASH)
                ->where('ledger_preview.1.credit_account', FinancialTransaction::ACCOUNT_CUSTOMER_LIABILITY)
            );
    }

    public function test_finance_dashboard_includes_last_7_days_analytics(): void
    {
        Carbon::setTestNow('2026-05-07 12:00:00');

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
            'provider_name' => 'Finance Analytics Provider',
            'booking_reference' => 'BK-FIN-ANL-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Analytics Hotel'],
            'currency' => 'USD',
            'total_amount' => 500.00,
            'request_payload' => ['hotel_name' => 'Analytics Hotel'],
        ]);

        $previousOrder = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Finance Analytics Previous Provider',
            'booking_reference' => 'BK-FIN-ANL-002',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['route' => 'DXB-CAI'],
            'currency' => 'USD',
            'total_amount' => 200.00,
            'request_payload' => ['route' => 'DXB-CAI'],
        ]);

        $paymentToday = FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '300.00',
            'currency' => 'USD',
            'source' => 'hotel_sa_payment',
        ]);

        $paymentToday->forceFill([
            'created_at' => Carbon::now()->copy()->subDay(),
            'updated_at' => Carbon::now()->copy()->subDay(),
        ])->save();

        $refundToday = FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_REFUND,
            'amount' => '50.00',
            'currency' => 'USD',
            'source' => 'hotel_sa_refund',
        ]);

        $refundToday->forceFill([
            'created_at' => Carbon::now()->copy()->subDay(),
            'updated_at' => Carbon::now()->copy()->subDay(),
        ])->save();

        $paymentEarlier = FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '120.00',
            'currency' => 'USD',
            'source' => 'flight_ae_payment',
        ]);

        $paymentEarlier->forceFill([
            'created_at' => Carbon::now()->copy()->subDays(3),
            'updated_at' => Carbon::now()->copy()->subDays(3),
        ])->save();

        $commissionToday = FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_COMMISSION,
            'amount' => '20.00',
            'currency' => 'USD',
            'source' => 'hotel_sa_commission',
        ]);

        $commissionToday->forceFill([
            'created_at' => Carbon::now()->copy()->subDays(2),
            'updated_at' => Carbon::now()->copy()->subDays(2),
        ])->save();

        $paymentPrevious = FinancialTransaction::query()->create([
            'order_id' => $previousOrder->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '200.00',
            'currency' => 'USD',
            'source' => 'flight_ae_payment_previous',
        ]);

        $paymentPrevious->forceFill([
            'created_at' => Carbon::now()->copy()->subDays(35),
            'updated_at' => Carbon::now()->copy()->subDays(35),
        ])->save();

        $this->actingAs($actor)
            ->get('/admin/finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/finance/pages/Index', false)
                ->where('dashboard.analytics', fn ($analytics): bool => collect($analytics)->keys()->values()->all() === ['time_series', 'segmentation', 'insights', 'bi'])
                ->where('dashboard.analytics.insights.revenue_last_7_days', '420.00')
                ->where('dashboard.analytics.insights.refunds_last_7_days', '50.00')
                ->has('dashboard.analytics.time_series.labels', 7)
                ->has('dashboard.analytics.time_series.datasets', 3)
                ->has('dashboard.analytics.bi')
                ->has('dashboard.analytics.segmentation.by_type', 3)
                ->has('dashboard.analytics.segmentation.by_source', 4)
                ->has('dashboard.analytics.segmentation.by_currency', 1)
                ->where('dashboard.analytics.segmentation.by_type.0.key', FinancialTransaction::TYPE_COMMISSION)
                ->where('dashboard.analytics.segmentation.by_type.0.count', 1)
                ->where('dashboard.analytics.segmentation.by_type.0.total', '20.00')
                ->where('dashboard.analytics.segmentation.by_type.1.key', FinancialTransaction::TYPE_PAYMENT)
                ->where('dashboard.analytics.segmentation.by_type.1.count', 2)
                ->where('dashboard.analytics.segmentation.by_type.1.total', '420.00')
                ->where('dashboard.analytics.segmentation.by_type.2.key', FinancialTransaction::TYPE_REFUND)
                ->where('dashboard.analytics.segmentation.by_type.2.count', 1)
                ->where('dashboard.analytics.segmentation.by_type.2.total', '50.00')
                ->where('dashboard.analytics.segmentation.by_source.0.key', 'flight_ae_payment')
                ->where('dashboard.analytics.segmentation.by_source.0.total', '120.00')
                ->where('dashboard.analytics.segmentation.by_source.1.key', 'hotel_sa_commission')
                ->where('dashboard.analytics.segmentation.by_source.1.total', '20.00')
                ->where('dashboard.analytics.segmentation.by_source.2.key', 'hotel_sa_payment')
                ->where('dashboard.analytics.segmentation.by_source.2.total', '300.00')
                ->where('dashboard.analytics.segmentation.by_source.3.key', 'hotel_sa_refund')
                ->where('dashboard.analytics.segmentation.by_source.3.total', '50.00')
                ->where('dashboard.analytics.segmentation.by_currency.0.key', 'USD')
                ->where('dashboard.analytics.segmentation.by_currency.0.count', 4)
                ->where('dashboard.analytics.segmentation.by_currency.0.total', '490.00')
                ->where('dashboard.analytics.time_series.labels.3', '2026-05-04')
                ->where('dashboard.analytics.time_series.labels.5', '2026-05-06')
                ->where('dashboard.analytics.time_series.datasets.0.label', 'Revenue')
                ->where('dashboard.analytics.time_series.datasets.0.key', 'payments')
                ->where('dashboard.analytics.time_series.datasets.0.data.3', '120.00')
                ->where('dashboard.analytics.time_series.datasets.0.data.5', '300.00')
                ->where('dashboard.analytics.time_series.datasets.1.label', 'Refunds')
                ->where('dashboard.analytics.time_series.datasets.1.key', 'refunds')
                ->where('dashboard.analytics.time_series.datasets.1.data.3', '0.00')
                ->where('dashboard.analytics.time_series.datasets.1.data.5', '50.00')
                ->where('dashboard.analytics.time_series.datasets.2.label', 'Net')
                ->where('dashboard.analytics.time_series.datasets.2.key', 'net')
                ->where('dashboard.analytics.time_series.datasets.2.data.5', '250.00')
                ->has('dashboard.analytics.bi.revenue_per_service', 2)
                ->where('dashboard.analytics.bi.revenue_per_service.0.key', Order::SERVICE_TYPE_FLIGHT)
                ->where('dashboard.analytics.bi.revenue_per_service.0.total', '320.00')
                ->where('dashboard.analytics.bi.revenue_per_service.1.key', Order::SERVICE_TYPE_HOTEL)
                ->where('dashboard.analytics.bi.revenue_per_service.1.total', '300.00')
                ->has('dashboard.analytics.bi.revenue_per_country', 2)
                ->where('dashboard.analytics.bi.revenue_per_country.0.key', 'AE')
                ->where('dashboard.analytics.bi.revenue_per_country.0.total', '320.00')
                ->where('dashboard.analytics.bi.revenue_per_country.1.key', 'SA')
                ->where('dashboard.analytics.bi.revenue_per_country.1.total', '300.00')
                ->where('dashboard.analytics.bi.refund_rate.percentage', '8.06')
                ->where('dashboard.analytics.bi.refund_rate.refunds_total', '50.00')
                ->where('dashboard.analytics.bi.refund_rate.payments_total', '620.00')
                ->where('dashboard.analytics.bi.average_order_value.amount', '310.00')
                ->where('dashboard.analytics.bi.average_order_value.orders_count', 2)
                ->has('dashboard.analytics.bi.transaction_mix', 3)
                ->where('dashboard.analytics.bi.transaction_mix.0.key', FinancialTransaction::TYPE_COMMISSION)
                ->where('dashboard.analytics.bi.transaction_mix.0.share', '20.00')
                ->where('dashboard.analytics.bi.transaction_mix.1.key', FinancialTransaction::TYPE_PAYMENT)
                ->where('dashboard.analytics.bi.transaction_mix.1.total', '620.00')
                ->where('dashboard.analytics.bi.transaction_mix.1.share', '60.00')
                ->where('dashboard.analytics.bi.transaction_mix.2.key', FinancialTransaction::TYPE_REFUND)
                ->where('dashboard.analytics.bi.transaction_mix.2.share', '20.00')
                ->where('dashboard.analytics.bi.monthly_growth.current_period', '420.00')
                ->where('dashboard.analytics.bi.monthly_growth.previous_period', '200.00')
                ->where('dashboard.analytics.bi.monthly_growth.change_amount', '220.00')
                ->where('dashboard.analytics.bi.monthly_growth.change_percentage', '110.00')
            );

        Carbon::setTestNow();
    }
}