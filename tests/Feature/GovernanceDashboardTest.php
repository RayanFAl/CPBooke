<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\LoyaltyHistory;
use App\Models\LoyaltyTier;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\RbacAuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GovernanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_governance_dashboard_aggregates_rbac_finance_notifications_and_loyalty_sections(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $admin->syncRolesByName(['read_only_analyst']);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Governance Provider',
            'booking_reference' => 'BK-GOV-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Governance Suites'],
            'currency' => 'USD',
            'total_amount' => 300.00,
            'request_payload' => ['hotel_name' => 'Governance Suites'],
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '300.00',
            'currency' => 'USD',
            'source' => 'governance_payment',
        ]);

        $tierFrom = LoyaltyTier::query()->create([
            'code' => 'gov_1',
            'name' => 'Gov Tier 1',
            'level' => 81,
            'sort_order' => 81,
            'is_active' => true,
            'is_default' => false,
        ]);
        $tierTo = LoyaltyTier::query()->create([
            'code' => 'gov_2',
            'name' => 'Gov Tier 2',
            'level' => 82,
            'sort_order' => 82,
            'is_active' => true,
            'is_default' => false,
        ]);

        LoyaltyHistory::withoutEvents(function () use ($customer, $tierFrom, $tierTo): void {
            LoyaltyHistory::query()->create([
                'user_id' => $customer->id,
                'from_tier_id' => $tierFrom->id,
                'to_tier_id' => $tierTo->id,
                'action' => LoyaltyHistory::ACTION_UPGRADED,
                'changed_at' => now(),
            ]);
        });

        NotificationLog::query()->create([
            'user_id' => $customer->id,
            'channel' => 'email',
            'template_code' => 'ORDER_CONFIRMED',
            'template_version' => 1,
            'notification_type' => 'order',
            'subject' => 'Order confirmed',
            'body' => 'Order confirmed body',
            'status' => NotificationLog::STATUS_FAILED,
            'response_payload' => ['error' => 'SMTP unavailable'],
        ]);

        RbacAuditLog::query()->create([
            'user_id' => $admin->id,
            'permission' => 'finance.view',
            'action' => 'finance.dashboard.viewed',
            'target_type' => 'finance_dashboard',
            'context' => ['filters' => []],
        ]);

        $this->actingAs($admin)
            ->get('/admin/governance/dashboard?module=notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/governance/pages/Index', false)
                ->where('filters.module', 'notifications')
                ->where('dashboard.rbac.summary_24h.events', 1)
                ->where('dashboard.notifications.kpi.value', 1)
                ->where('dashboard.notifications.events.0.status', NotificationLog::STATUS_FAILED)
                ->where('dashboard.loyalty.summary_24h.upgrades', 1)
                ->where('dashboard.finance.kpi.label', 'Finance anomalies')
            );
    }

    public function test_governance_dashboard_is_protected_by_governance_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $supportAgent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $supportAgent->syncRolesByName(['support_agent']);

        $this->actingAs($supportAgent)
            ->get('/admin/governance/dashboard')
            ->assertForbidden();
    }
}
