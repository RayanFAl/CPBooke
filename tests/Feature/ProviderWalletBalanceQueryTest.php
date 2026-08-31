<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderApiConfig;
use App\Models\ProviderWallet;
use App\Models\Settlement;
use App\Models\User;
use App\Modules\Wallets\Services\ProviderWalletBalanceQueryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProviderWalletBalanceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_returns_wallets_without_persisting_to_database(): void
    {
        config([
            'wallets.provider_balance.tenant' => 'median',
            'wallets.default_environment' => 'production',
        ]);

        $provider = Provider::query()->create([
            'name' => 'Atom',
            'key' => 'atom',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        ProviderApiConfig::query()->create([
            'provider_id' => $provider->id,
            'environment' => 'production',
            'base_url' => 'https://agency.atom.ly/agency/median/api',
            'auth_type' => ProviderApiConfig::AUTH_BEARER_TOKEN,
            'access_token' => 'test-token',
            'status' => ProviderApiConfig::STATUS_ACTIVE,
        ]);

        Http::fake([
            'https://agency.atom.ly/agency/median/api/agency/median/api/v1/wallet/balance' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    ['currency' => 'LYD', 'balance' => 12500.5],
                    ['currency' => 'USD', 'balance' => 200],
                ],
            ]),
        ]);

        $service = app(ProviderWalletBalanceQueryService::class);
        $result = $service->fetchForProvider($provider);

        $this->assertTrue($result['available']);
        $this->assertNull($result['error']);
        $this->assertSame(2, $result['wallet_count']);
        $this->assertSame([
            ['currency' => 'LYD', 'balance' => '12500.50'],
            ['currency' => 'USD', 'balance' => '200.00'],
        ], $result['wallets']);
        $this->assertNotNull($result['fetched_at']);

        $this->assertDatabaseMissing('provider_wallets', [
            'provider_id' => $provider->id,
            'balance' => '12500.50',
        ]);
    }

    public function test_fetch_returns_error_when_api_not_configured(): void
    {
        config([
            'provider_api.base_url' => '',
            'provider_api.access_token' => '',
            'wallets.provider_balance.tenant' => '',
        ]);

        $provider = Provider::query()->create([
            'name' => 'Atom',
            'key' => 'atom',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        $service = app(ProviderWalletBalanceQueryService::class);
        $result = $service->fetchForProvider($provider);

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('PROVIDER_API_BASE_URL', $result['error']);
        $this->assertSame(0, $result['wallet_count']);
        $this->assertSame([], $result['wallets']);
    }

    public function test_fetch_auto_syncs_api_config_from_env(): void
    {
        config([
            'provider_api.base_url' => 'https://agency.atom.ly',
            'provider_api.access_token' => 'test-token',
            'provider_api.environment' => 'production',
            'provider_api.auth_type' => ProviderApiConfig::AUTH_BEARER_TOKEN,
            'wallets.provider_balance.tenant' => 'median',
            'wallets.default_environment' => 'production',
        ]);

        $provider = Provider::query()->create([
            'name' => 'Atom',
            'key' => 'atom',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        Http::fake([
            'https://agency.atom.ly/agency/median/api/v1/wallet/balance' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [],
            ]),
        ]);

        $service = app(ProviderWalletBalanceQueryService::class);
        $result = $service->fetchForProvider($provider);

        $this->assertTrue($result['available']);
        $this->assertDatabaseHas('provider_api_configs', [
            'provider_id' => $provider->id,
            'environment' => 'production',
        ]);
    }

    public function test_settlement_show_includes_live_provider_wallets(): void
    {
        config([
            'wallets.provider_balance.tenant' => 'median',
            'wallets.default_environment' => 'production',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $admin->syncRolesByName(['super_admin']);

        $provider = Provider::query()->create([
            'name' => 'Atom',
            'key' => 'atom',
            'status' => Provider::STATUS_ACTIVE,
            'default_currency' => 'LYD',
        ]);

        ProviderApiConfig::query()->create([
            'provider_id' => $provider->id,
            'environment' => 'production',
            'base_url' => 'https://agency.atom.ly/agency/median/api',
            'auth_type' => ProviderApiConfig::AUTH_BEARER_TOKEN,
            'access_token' => 'test-token',
            'status' => ProviderApiConfig::STATUS_ACTIVE,
        ]);

        $settlement = Settlement::query()->create([
            'provider_id' => $provider->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'currency' => 'LYD',
            'status' => Settlement::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        Http::fake([
            'https://agency.atom.ly/agency/median/api/agency/median/api/v1/wallet/balance' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    ['currency' => 'LYD', 'balance' => 5000],
                ],
            ]),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settlements.show', $settlement, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/settlements/pages/Show', false)
                ->where('provider_api_wallets.available', true)
                ->where('provider_api_wallets.wallet_count', 1)
                ->where('provider_api_wallets.wallets.0.currency', 'LYD')
                ->where('provider_api_wallets.wallets.0.balance', '5000.00')
            );
    }
}
