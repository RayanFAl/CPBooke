<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderApiEvent;
use App\Models\ProviderWallet;
use App\Models\Settlement;
use App\Models\User;
use App\Modules\ProviderHealth\Services\ProviderHealthService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProviderHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_manager_can_view_provider_health_noc(): void
    {
        $actor = $this->makeAdmin('operations_manager');
        $provider = $this->createProviderWithWallet(balance: '12000.00');

        ProviderApiEvent::query()->create([
            'provider_id' => $provider->id,
            'event_type' => ProviderApiEvent::TYPE_SYNC_SUCCESS,
            'latency_ms' => 180,
            'success' => true,
            'message' => 'ok',
            'created_at' => now(),
        ]);

        $this->actingAs($actor)
            ->get(route('admin.provider-health.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/provider-health/pages/Index', false)
                ->where('dashboard.summary.providers_total', 1)
                ->has('dashboard.providers', 1)
                ->where('dashboard.providers.0.key', 'booknow')
                ->where('dashboard.providers.0.api_status', 'online'));
    }

    public function test_low_wallet_and_failures_produce_critical_alerts_and_lower_score(): void
    {
        $provider = $this->createProviderWithWallet(balance: '80.00', threshold: '1000.00');

        foreach (range(1, 8) as $i) {
            ProviderApiEvent::query()->create([
                'provider_id' => $provider->id,
                'event_type' => ProviderApiEvent::TYPE_SYNC_FAILURE,
                'latency_ms' => 4000,
                'success' => false,
                'message' => 'timeout '.$i,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        ProviderApiEvent::query()->create([
            'provider_id' => $provider->id,
            'event_type' => ProviderApiEvent::TYPE_SYNC_SUCCESS,
            'latency_ms' => 500,
            'success' => true,
            'message' => 'ok',
            'created_at' => now()->subMinutes(20),
        ]);

        Settlement::query()->create([
            'provider_id' => $provider->id,
            'period_start' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'period_end' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            'currency' => 'LYD',
            'status' => Settlement::STATUS_OPEN,
            'expected_cost' => '1000.00',
            'wallet_debit_total' => '1000.00',
            'supplier_invoice_total' => '0.00',
            'difference' => '-1000.00',
            'orders_count' => 1,
            'matched_count' => 0,
            'review_count' => 1,
        ]);

        $card = app(ProviderHealthService::class)->buildProviderCard($provider->fresh('wallets'));

        $this->assertSame('critical', $card['health_band']);
        $this->assertLessThan(75, $card['health_score']);
        $this->assertNotEmpty($card['alerts']);
        $this->assertTrue(collect($card['alerts'])->contains(fn (array $alert): bool => $alert['code'] === 'wallet_low'));
    }

    public function test_healthy_provider_scores_excellent_band(): void
    {
        $provider = $this->createProviderWithWallet(balance: '25000.00');

        foreach (range(1, 5) as $i) {
            ProviderApiEvent::query()->create([
                'provider_id' => $provider->id,
                'event_type' => ProviderApiEvent::TYPE_SYNC_SUCCESS,
                'latency_ms' => 120 + $i,
                'success' => true,
                'message' => 'ok',
                'created_at' => now()->subMinutes($i),
            ]);
        }

        Settlement::query()->create([
            'provider_id' => $provider->id,
            'period_start' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'period_end' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            'currency' => 'LYD',
            'status' => Settlement::STATUS_CLOSED,
            'expected_cost' => '1000.00',
            'wallet_debit_total' => '1000.00',
            'supplier_invoice_total' => '1000.00',
            'difference' => '0.00',
            'orders_count' => 10,
            'matched_count' => 10,
            'review_count' => 0,
            'closed_at' => now()->subDays(2),
        ]);

        $card = app(ProviderHealthService::class)->buildProviderCard($provider->fresh('wallets'));

        $this->assertSame('online', $card['api_status']);
        $this->assertGreaterThanOrEqual(95, $card['health_score']);
        $this->assertSame('excellent', $card['health_band']);
        $this->assertSame([], $card['alerts']);
    }

    public function test_support_agent_without_permission_is_forbidden_for_team_member(): void
    {
        $actor = $this->makeAdmin('team_member');

        $this->actingAs($actor)
            ->get(route('admin.provider-health.index', absolute: false))
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

    private function createProviderWithWallet(string $balance, string $threshold = '1000.00'): Provider
    {
        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => Provider::KEY_BOOKNOW,
            'status' => Provider::STATUS_ACTIVE,
            'integration_status' => Provider::INTEGRATION_LIVE,
            'default_currency' => 'LYD',
            'credit_limit' => '5000.00',
            'settlement_cycle' => Provider::SETTLEMENT_MONTHLY,
        ]);

        ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => 'production',
            'balance' => $balance,
            'low_balance_threshold' => $threshold,
            'allow_negative' => true,
            'is_active' => true,
        ]);

        return $provider;
    }
}
