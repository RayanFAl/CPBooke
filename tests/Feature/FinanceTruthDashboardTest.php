<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\User;
use App\Modules\Admin\Finance\Services\FinancialConsistencyService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceTruthDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_dashboard_exposes_reconciliation_kpis_and_order_drilldown(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'country' => 'SA',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['finance_manager']);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Truth Provider',
            'booking_reference' => 'BK-TRUTH-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Truth Suites'],
            'currency' => 'USD',
            'total_amount' => 500.00,
            'request_payload' => ['hotel_name' => 'Truth Suites'],
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '500.00',
            'currency' => 'USD',
            'source' => 'hotel_sa_payment',
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_COMMISSION,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '60.00',
            'currency' => 'USD',
            'source' => 'hotel_sa_commission',
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_REFUND,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '50.00',
            'currency' => 'USD',
            'source' => 'hotel_sa_refund',
        ]);

        $this->actingAs($actor)
            ->get('/admin/finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/finance/pages/Index', false)
                ->where('dashboard.kpis.gross_revenue', '500.00')
                ->where('dashboard.kpis.recognized_revenue', '60.00')
                ->where('dashboard.kpis.refunds', '50.00')
                ->where('dashboard.kpis.net_cash', '450.00')
                ->where('dashboard.kpis.gross_profit', '10.00')
                ->where('dashboard.reconciliation.counts.total', 0)
                ->where('dashboard.intelligence.profit_per_country.0.key', 'SA')
                ->where('dashboard.intelligence.commission_breakdown_per_provider.0.key', 'Truth Provider')
                ->where('dashboard.drilldown.orders.0.booking_reference', 'BK-TRUTH-001')
                ->where('dashboard.drilldown.orders.0.net_cash', '450.00')
                ->where('dashboard.drilldown.orders.0.gross_profit', '10.00')
            );
    }

    public function test_financial_reconciliation_detects_and_repairs_missing_ledger_entries(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Repair Provider',
            'booking_reference' => 'BK-REPAIR-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['airline' => 'Repair Air'],
            'currency' => 'USD',
            'total_amount' => 200.00,
            'request_payload' => ['airline' => 'Repair Air'],
        ]);

        $transaction = FinancialTransaction::withoutEvents(function () use ($order) {
            return FinancialTransaction::query()->create([
                'order_id' => $order->id,
                'type' => FinancialTransaction::TYPE_PAYMENT,
                'status' => FinancialTransaction::STATUS_EXECUTED,
                'amount' => '200.00',
                'currency' => 'USD',
                'source' => 'repair_payment',
            ]);
        });

        $summaryBefore = app(FinancialConsistencyService::class)->summarize();
        $repairSummary = app(FinancialConsistencyService::class)->reconcile();
        $summaryAfter = app(FinancialConsistencyService::class)->summarize();

        $this->assertSame(1, $summaryBefore['counts']['transactions_missing_ledger']);
        $this->assertSame(1, $repairSummary['missing_ledger_entries_repaired']);
        $this->assertSame(0, $summaryAfter['counts']['transactions_missing_ledger']);
        $this->assertDatabaseCount('financial_ledger_entries', 2);
        $this->assertNotNull($transaction->fresh()->ledgerEntries()->first());
    }

    public function test_finance_csv_export_streams_order_drilldown_rows(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'country' => 'AE',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['finance_manager']);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'CSV Provider',
            'booking_reference' => 'BK-CSV-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_INSURANCE,
            'details' => ['insurance_type' => 'travel_medical'],
            'currency' => 'USD',
            'total_amount' => 80.00,
            'request_payload' => ['insurance_type' => 'travel_medical'],
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '80.00',
            'currency' => 'USD',
            'source' => 'insurance_ae_payment',
        ]);

        $response = $this->actingAs($actor)
            ->get(route('admin.finance.export.csv', absolute: false));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('BK-CSV-001', $response->streamedContent());
        $this->assertStringContainsString('CSV Provider', $response->streamedContent());
    }
}