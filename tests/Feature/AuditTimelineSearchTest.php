<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\User;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Audit\Services\EntityTimelineService;
use App\Modules\Audit\Services\GlobalSearchService;
use App\Modules\Wallets\Services\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditTimelineSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_manager_can_view_audit_center(): void
    {
        $actor = $this->makeAdmin('finance_manager');

        app(AuditRecorder::class)->success(
            AuditLog::MODULE_WALLETS,
            'wallet.deposited',
            'Wallet deposit test',
            AuditLog::ENTITY_PROVIDER_WALLET,
            1,
            $actor,
            ['balance' => '0.00'],
            ['balance' => '100.00'],
        );

        $this->actingAs($actor)
            ->get(route('admin.audit.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/audit/pages/Index', false)
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'wallet.deposited'));
    }

    public function test_support_agent_can_use_global_search_for_orders(): void
    {
        $actor = $this->makeAdmin('support_agent');
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'full_name' => 'Sara Searchable',
            'email' => 'sara.search@example.com',
            'phone' => '0912345678',
        ]);

        Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'BookNow',
            'booking_reference' => 'BN-SEARCH-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['pnr' => 'PNRABC'],
            'currency' => 'LYD',
            'total_amount' => '250.00',
            'request_payload' => [],
        ]);

        $this->actingAs($actor)
            ->get(route('admin.search.index', ['q' => 'BN-SEARCH-001'], absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/search/pages/Index', false)
                ->where('result.total', fn ($total) => $total >= 1)
                ->has('result.groups.orders'));
    }

    public function test_wallet_deposit_writes_audit_log_and_timeline(): void
    {
        $actor = $this->makeAdmin('finance_manager');
        $provider = Provider::query()->create([
            'name' => 'Audit Provider',
            'key' => 'audit-provider',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        $wallet = ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => ProviderWallet::ENVIRONMENT_PRODUCTION,
            'balance' => '0.00',
            'allow_negative' => true,
            'is_active' => true,
        ]);

        app(WalletService::class)->deposit($wallet, 50, $actor, [
            'description' => 'Audit deposit',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => AuditLog::MODULE_WALLETS,
            'action' => 'wallet.deposited',
            'entity_type' => AuditLog::ENTITY_PROVIDER_WALLET,
            'entity_id' => $wallet->id,
            'status' => AuditLog::STATUS_SUCCESS,
        ]);

        $timeline = app(EntityTimelineService::class)->forProviderWallet($wallet->fresh());
        $labels = collect($timeline)->pluck('label')->implode(' ');
        $this->assertStringContainsString('Wallet Deposit', $labels);
    }

    public function test_global_search_service_finds_customer_by_email(): void
    {
        User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'full_name' => 'Find Me',
            'email' => 'find.me@example.com',
        ]);

        $result = app(GlobalSearchService::class)->search('find.me@example.com');

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertArrayHasKey('customers', $result['groups']);
    }

    public function test_team_member_cannot_view_audit_center(): void
    {
        $actor = $this->makeAdmin('team_member');

        $this->actingAs($actor)
            ->get(route('admin.audit.index', absolute: false))
            ->assertForbidden();
    }

    private function makeAdmin(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $user->syncRolesByName([$role]);

        return $user;
    }
}
