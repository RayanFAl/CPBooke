<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomerWallet;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderApiConfig;
use App\Models\ProviderWallet;
use App\Models\User;
use App\Modules\Providers\Services\ProviderApiLogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProviderApiConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_update_provider_api_config(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        $this->actingAs($admin)
            ->post(route('admin.suppliers.api-config.upsert', $provider), [
                'environment' => 'sandbox',
                'base_url' => 'https://sandbox.provider.test',
                'auth_type' => ProviderApiConfig::AUTH_API_KEY_SECRET,
                'api_key' => 'sandbox-key',
                'api_secret' => 'sandbox-secret',
                'timeout' => 20,
                'status' => ProviderApiConfig::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.suppliers.show', $provider));

        $config = ProviderApiConfig::query()->where('provider_id', $provider->id)->firstOrFail();
        $this->assertSame('sandbox', $config->environment);
        $this->assertSame('sandbox-key', $config->api_key);
        $this->assertSame('sandbox-secret', $config->api_secret);
        $this->assertNotSame('sandbox-secret', $config->getAttributes()['api_secret']);

        $this->actingAs($admin)
            ->post(route('admin.suppliers.api-config.upsert', $provider), [
                'environment' => 'sandbox',
                'base_url' => 'https://sandbox.provider.test/v2',
                'auth_type' => ProviderApiConfig::AUTH_API_KEY_SECRET,
                'timeout' => 25,
                'status' => ProviderApiConfig::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.suppliers.show', $provider));

        $config->refresh();
        $this->assertSame('https://sandbox.provider.test/v2', $config->base_url);
        $this->assertSame('sandbox-key', $config->api_key);
        $this->assertSame('sandbox-secret', $config->api_secret);
    }

    public function test_production_config_requires_confirmation(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        $this->actingAs($admin)
            ->post(route('admin.suppliers.api-config.upsert', $provider), [
                'environment' => 'production',
                'base_url' => 'https://api.provider.test',
                'auth_type' => ProviderApiConfig::AUTH_API_KEY,
                'api_key' => 'prod-key',
                'confirm_production' => false,
            ])
            ->assertSessionHasErrors('confirm_production');

        $this->actingAs($admin)
            ->post(route('admin.suppliers.api-config.upsert', $provider), [
                'environment' => 'production',
                'base_url' => 'https://api.provider.test',
                'auth_type' => ProviderApiConfig::AUTH_API_KEY,
                'api_key' => 'prod-key',
                'confirm_production' => true,
            ])
            ->assertRedirect(route('admin.suppliers.show', $provider));

        $this->assertDatabaseHas('provider_api_configs', [
            'provider_id' => $provider->id,
            'environment' => 'production',
        ]);
    }

    public function test_api_config_can_be_disabled(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        ProviderApiConfig::query()->create([
            'provider_id' => $provider->id,
            'environment' => 'sandbox',
            'base_url' => 'https://sandbox.provider.test',
            'auth_type' => ProviderApiConfig::AUTH_API_KEY,
            'api_key' => 'key',
            'status' => ProviderApiConfig::STATUS_ACTIVE,
            'timeout' => 30,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.suppliers.api-config.disable', [$provider, 'sandbox']))
            ->assertRedirect(route('admin.suppliers.show', $provider));

        $this->assertDatabaseHas('provider_api_configs', [
            'provider_id' => $provider->id,
            'environment' => 'sandbox',
            'status' => ProviderApiConfig::STATUS_DISABLED,
        ]);
    }

    public function test_secrets_are_masked_on_supplier_show_page(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        ProviderApiConfig::query()->create([
            'provider_id' => $provider->id,
            'environment' => 'sandbox',
            'base_url' => 'https://sandbox.provider.test',
            'auth_type' => ProviderApiConfig::AUTH_BEARER_TOKEN,
            'access_token' => 'super-secret-token',
            'status' => ProviderApiConfig::STATUS_ACTIVE,
            'timeout' => 30,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.suppliers.show', $provider))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/suppliers/pages/Show', false)
                ->where('supplier.id', $provider->id)
            );
    }

    public function test_unauthorized_user_cannot_manage_api_config(): void
    {
        $viewer = $this->makeAdmin('read_only_analyst');
        $provider = $this->makeProvider();

        $this->actingAs($viewer)
            ->post(route('admin.suppliers.api-config.upsert', $provider), [
                'environment' => 'sandbox',
                'base_url' => 'https://sandbox.provider.test',
                'auth_type' => ProviderApiConfig::AUTH_API_KEY,
                'api_key' => 'key',
            ])
            ->assertForbidden();
    }

    public function test_credential_view_is_audited(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        ProviderApiConfig::query()->create([
            'provider_id' => $provider->id,
            'environment' => 'sandbox',
            'base_url' => 'https://sandbox.provider.test',
            'auth_type' => ProviderApiConfig::AUTH_API_KEY,
            'api_key' => 'key',
            'status' => ProviderApiConfig::STATUS_ACTIVE,
            'timeout' => 30,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.suppliers.api-config.audit-credentials', [$provider, 'sandbox']))
            ->assertRedirect(route('admin.suppliers.show', $provider));

        $this->assertDatabaseHas('audit_logs', [
            'module' => AuditLog::MODULE_PROVIDERS,
            'action' => 'provider_credentials.viewed',
            'entity_type' => AuditLog::ENTITY_PROVIDER,
            'entity_id' => $provider->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_connection_test_success_and_failure(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        $successConfig = ProviderApiConfig::query()->create([
            'provider_id' => $provider->id,
            'environment' => 'sandbox',
            'base_url' => 'https://sandbox.provider.test',
            'auth_type' => ProviderApiConfig::AUTH_API_KEY,
            'api_key' => 'valid-key',
            'status' => ProviderApiConfig::STATUS_ACTIVE,
            'timeout' => 5,
        ]);

        Http::fake([
            'https://sandbox.provider.test' => Http::response(['ok' => true], 200),
            'https://sandbox.provider.test/*' => Http::response(['ok' => true], 200),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.suppliers.api-config.test', [$provider, 'sandbox']))
            ->assertRedirect(route('admin.suppliers.show', $provider))
            ->assertSessionHas('success');

        $successConfig->refresh();
        $this->assertSame(ProviderApiConfig::TEST_STATUS_SUCCESS, $successConfig->last_test_status);
        $this->assertSame(200, $successConfig->last_test_http_status);

        $failureProvider = $this->makeProvider('booknow_hotels');
        $failureConfig = ProviderApiConfig::query()->create([
            'provider_id' => $failureProvider->id,
            'environment' => 'production',
            'base_url' => 'https://api.provider.test',
            'auth_type' => ProviderApiConfig::AUTH_BEARER_TOKEN,
            'access_token' => 'invalid-token',
            'status' => ProviderApiConfig::STATUS_ACTIVE,
            'timeout' => 5,
        ]);

        Http::fake([
            'https://api.provider.test' => Http::response(['error' => 'Unauthorized'], 401),
            'https://api.provider.test/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.suppliers.api-config.test', [$failureProvider, 'production']))
            ->assertRedirect(route('admin.suppliers.show', $failureProvider))
            ->assertSessionHas('error');

        $failureConfig->refresh();
        $this->assertSame(ProviderApiConfig::TEST_STATUS_FAILED, $failureConfig->last_test_status);
        $this->assertSame(401, $failureConfig->last_test_http_status);
        $this->assertSame('Authentication failed.', $failureConfig->last_test_message);
    }

    public function test_connection_test_uses_correct_environment_endpoint(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        ProviderApiConfig::query()->create([
            'provider_id' => $provider->id,
            'environment' => 'sandbox',
            'base_url' => 'https://sandbox.provider.test',
            'auth_type' => ProviderApiConfig::AUTH_API_KEY,
            'api_key' => 'sandbox-key',
            'status' => ProviderApiConfig::STATUS_ACTIVE,
            'timeout' => 5,
        ]);

        ProviderApiConfig::query()->create([
            'provider_id' => $provider->id,
            'environment' => 'production',
            'base_url' => 'https://api.provider.test',
            'auth_type' => ProviderApiConfig::AUTH_API_KEY,
            'api_key' => 'prod-key',
            'status' => ProviderApiConfig::STATUS_ACTIVE,
            'timeout' => 5,
        ]);

        Http::fake([
            'https://api.provider.test' => Http::response(['ok' => true], 200),
            'https://api.provider.test/*' => Http::response(['ok' => true], 200),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.suppliers.api-config.test', [$provider, 'production']))
            ->assertRedirect(route('admin.suppliers.show', $provider));

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.provider.test'));
    }

    public function test_services_can_be_enabled_and_disabled(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        $this->actingAs($admin)
            ->post(route('admin.suppliers.services.sync', $provider), [
                'services' => [
                    ['service' => 'flight', 'enabled' => true],
                    ['service' => 'hotel', 'enabled' => true],
                    ['service' => 'insurance', 'enabled' => false],
                    ['service' => 'esim', 'enabled' => false],
                    ['service' => 'visa', 'enabled' => false],
                    ['service' => 'activities', 'enabled' => false],
                    ['service' => 'transfers', 'enabled' => false],
                ],
            ])
            ->assertRedirect(route('admin.suppliers.show', $provider));

        $this->assertDatabaseHas('provider_services', [
            'provider_id' => $provider->id,
            'service' => 'flight',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('provider_services', [
            'provider_id' => $provider->id,
            'service' => 'hotel',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('provider_services', [
            'provider_id' => $provider->id,
            'service' => 'insurance',
            'enabled' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.suppliers.services.sync', $provider), [
                'services' => [
                    ['service' => 'flight', 'enabled' => false],
                    ['service' => 'hotel', 'enabled' => true],
                    ['service' => 'insurance', 'enabled' => false],
                    ['service' => 'esim', 'enabled' => false],
                    ['service' => 'visa', 'enabled' => false],
                    ['service' => 'activities', 'enabled' => false],
                    ['service' => 'transfers', 'enabled' => false],
                ],
            ])
            ->assertRedirect(route('admin.suppliers.show', $provider));

        $this->assertDatabaseHas('provider_services', [
            'provider_id' => $provider->id,
            'service' => 'flight',
            'enabled' => false,
        ]);
    }

    public function test_provider_api_logs_and_monitoring_are_visible_in_supplier_show(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        app(ProviderApiLogService::class)->record(
            provider: $provider,
            endpointKey: 'flight.search',
            statusCode: 200,
            success: true,
            responseTimeMs: 210,
            requestBody: ['origin' => 'TIP', 'destination' => 'IST'],
            responseBody: ['offers' => 12],
            correlationId: 'CP-8F92A',
            referenceType: 'order',
            referenceId: '1250',
        );

        app(ProviderApiLogService::class)->record(
            provider: $provider,
            endpointKey: 'flight.search',
            statusCode: 401,
            success: false,
            responseTimeMs: 180,
            requestBody: ['origin' => 'TIP', 'destination' => 'IST'],
            responseBody: ['error' => 'Unauthorized'],
            errorMessage: 'Authentication failed.',
            correlationId: 'CP-8F92A',
            referenceType: 'order',
            referenceId: '1250',
        );

        $this->actingAs($admin)
            ->get(route('admin.suppliers.show', $provider))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/suppliers/pages/Show', false)
                ->where('supplier.id', $provider->id)
            );
    }

    public function test_supplier_api_logs_can_be_filtered_by_service_status_and_date(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = $this->makeProvider();

        app(ProviderApiLogService::class)->record(
            provider: $provider,
            endpointKey: 'flight.search',
            statusCode: 200,
            success: true,
            responseTimeMs: 200,
            correlationId: 'CP-FILTER-1',
        );

        app(ProviderApiLogService::class)->record(
            provider: $provider,
            endpointKey: 'hotel.search',
            statusCode: 500,
            success: false,
            responseTimeMs: 800,
            errorMessage: 'Provider returned a server error.',
            correlationId: 'CP-FILTER-2',
        );

        $this->actingAs($admin)
            ->get(route('admin.suppliers.show', [
                'supplier' => $provider->id,
                'service' => 'hotel',
                'success' => 'failed',
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/suppliers/pages/Show', false)
                ->where('supplier.id', $provider->id)
            );
    }

    public function test_regression_modules_still_work_after_provider_api_changes(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-REG-000001',
            'currency' => 'LYD',
            'balance' => 1000,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        $provider = $this->makeProvider();
        ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => 'production',
            'balance' => 5000,
            'allow_negative' => false,
            'is_active' => true,
        ]);

        Order::query()->create([
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
            'provider_name' => $provider->name,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 550,
            'request_payload' => ['test' => true],
        ]);

        $this->assertDatabaseCount('customer_wallets', 1);
        $this->assertDatabaseCount('provider_wallets', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('provider_api_logs', 0);
    }

    private function makeAdmin(string $role): User
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName([$role]);

        return $admin;
    }

    private function makeProvider(string $key = 'booknow'): Provider
    {
        return Provider::query()->create([
            'name' => 'BookNow',
            'key' => $key,
            'status' => Provider::STATUS_ACTIVE,
            'default_currency' => 'LYD',
            'integration_status' => Provider::INTEGRATION_SANDBOX,
        ]);
    }
}
