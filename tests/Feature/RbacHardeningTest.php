<?php

namespace Tests\Feature;

use App\Models\LoyaltyTier;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\RbacAuditLog;
use App\Models\User;
use App\Modules\Admin\Finance\Services\FinanceReportingService;
use App\Modules\Admin\Loyalty\Services\LoyaltyAdminService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_analyst_can_view_finance_dashboard_but_cannot_reconcile(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $analyst = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $analyst->syncRolesByName(['read_only_analyst']);

        $this->actingAs($analyst)
            ->get('/admin/finance')
            ->assertOk();

        $this->actingAs($analyst)
            ->post('/admin/finance/reconcile')
            ->assertForbidden();
    }

    public function test_settings_route_is_permission_protected_not_role_string_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $admin->syncRolesByName(['admin']);

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertForbidden();

        $superAdmin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $superAdmin->syncRolesByName(['super_admin']);

        $this->actingAs($superAdmin)
            ->get('/admin/settings')
            ->assertOk();
    }

    public function test_finance_reporting_service_enforces_permission_when_actor_context_exists(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $supportAgent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $supportAgent->syncRolesByName(['support_agent']);

        $this->actingAs($supportAgent);

        $this->expectException(AuthorizationException::class);

        app(FinanceReportingService::class)->dashboard();
    }

    public function test_loyalty_admin_service_enforces_granular_update_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $financeManager = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $financeManager->syncRolesByName(['finance_manager']);

        $tier = LoyaltyTier::query()->create([
            'code' => 'rbac_tier',
            'name' => 'RBAC Tier',
            'level' => 70,
            'sort_order' => 70,
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->actingAs($financeManager);

        $this->expectException(AuthorizationException::class);

        app(LoyaltyAdminService::class)->updateTier($tier, [
            'name' => 'Blocked Update',
        ]);
    }

    public function test_audit_logs_record_sensitive_admin_actions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $financeManager = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $financeManager->syncRolesByName(['finance_manager']);

        $superAdmin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $superAdmin->syncRolesByName(['super_admin']);

        $template = NotificationTemplate::query()->create([
            'code' => 'RBAC_TEMPLATE',
            'name' => 'RBAC Template',
            'subject' => 'Subject',
            'body' => 'Body',
            'channels' => ['email'],
            'variables' => [],
            'version' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($financeManager)
            ->get('/admin/finance')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->put(route('admin.notifications.templates.update', $template, absolute: false), [
                'name' => 'RBAC Template Updated',
                'subject' => 'Updated Subject',
                'body' => 'Updated Body',
                'channels' => ['email'],
                'variables' => [],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.notifications.index', absolute: false));

        $this->assertDatabaseHas('rbac_audit_logs', [
            'user_id' => $financeManager->id,
            'permission' => 'finance.view',
            'action' => 'finance.dashboard.viewed',
        ]);
        $this->assertDatabaseHas('rbac_audit_logs', [
            'user_id' => $superAdmin->id,
            'permission' => 'notifications.manage-templates',
            'action' => 'notifications.template.updated',
            'target_type' => 'notification_template',
            'target_id' => $template->id,
        ]);

        $this->assertGreaterThanOrEqual(2, RbacAuditLog::query()->count());
    }
}