<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExchangeRate;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserNotification;
use App\Modules\Admin\ExchangeRates\Events\ExchangeRateUpdated;
use App\Modules\ExchangeRates\Services\ExchangeRateService;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\ExchangeRateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ExchangeRatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ExchangeRateSeeder::class);
    }

    public function test_api_returns_only_lyd_usd_eur_rates(): void
    {
        $response = $this->getJson('/api/v1/currency/rates');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.base_currency', 'LYD')
            ->assertJsonPath('data.rates.LYD', 1)
            ->assertJsonPath('data.rates.USD', 9.385)
            ->assertJsonPath('data.rates.EUR', 10.89);

        $rates = $response->json('data.rates');
        $this->assertSame(['LYD', 'USD', 'EUR'], array_keys($rates));
    }

    public function test_api_converts_eur_to_usd_through_lyd(): void
    {
        $response = $this->postJson('/api/v1/currency/convert', [
            'amount' => 100,
            'from' => 'EUR',
            'to' => 'USD',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 100)
            ->assertJsonPath('data.from', 'EUR')
            ->assertJsonPath('data.to', 'USD')
            ->assertJsonPath('data.base_currency', 'LYD');

        $this->assertEqualsWithDelta(116.03622802, (float) $response->json('data.converted_amount'), 0.00000001);
        $this->assertEqualsWithDelta(1.16036228, (float) $response->json('data.rate'), 0.00000001);
    }

    public function test_api_converts_usd_to_eur_through_lyd(): void
    {
        $response = $this->postJson('/api/v1/currency/convert', [
            'amount' => 100,
            'from' => 'USD',
            'to' => 'EUR',
        ]);

        $response->assertOk();
        $this->assertEqualsWithDelta(86.17998163, (float) $response->json('data.converted_amount'), 0.00000001);
    }

    public function test_api_rejects_unsupported_currency(): void
    {
        $this->postJson('/api/v1/currency/convert', [
            'amount' => 10,
            'from' => 'GBP',
            'to' => 'LYD',
        ])->assertStatus(422);
    }

    public function test_admin_without_permission_cannot_view_exchange_rates_page(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_TEAM_MEMBER]);

        $this->actingAs($actor)
            ->get(route('admin.exchange-rates.index'))
            ->assertForbidden();
    }

    public function test_admin_can_update_usd_and_eur_rates_and_clears_cache(): void
    {
        Event::fake([ExchangeRateUpdated::class]);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_ADMIN]);

        Cache::put(ExchangeRateService::CACHE_KEY, ['rates' => [], 'updated_at' => null], 60);

        $this->actingAs($actor)
            ->put(route('admin.exchange-rates.update'), [
                'usd_rate_to_lyd' => 6.60,
                'eur_rate_to_lyd' => 7.75,
            ])
            ->assertRedirect(route('admin.exchange-rates.index'));

        $this->assertDatabaseHas('exchange_rates', [
            'currency_code' => 'USD',
            'rate_to_lyd' => '6.60000000',
        ]);
        $this->assertDatabaseHas('exchange_rates', [
            'currency_code' => 'EUR',
            'rate_to_lyd' => '7.75000000',
        ]);
        $this->assertDatabaseHas('exchange_rates', [
            'currency_code' => 'LYD',
            'rate_to_lyd' => '1.00000000',
        ]);

        $this->assertNull(Cache::get(ExchangeRateService::CACHE_KEY));

        Event::assertDispatched(ExchangeRateUpdated::class, 2);

        $this->assertDatabaseHas('rbac_audit_logs', [
            'action' => 'exchange_rates.updated',
            'permission' => 'exchange-rates.manage',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => AuditLog::MODULE_EXCHANGE_RATES,
            'action' => 'exchange_rate.updated',
            'entity_type' => AuditLog::ENTITY_EXCHANGE_RATE,
            'status' => AuditLog::STATUS_SUCCESS,
        ]);
    }

    public function test_lyd_rate_cannot_be_changed_via_admin_update(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        ExchangeRate::query()->where('currency_code', 'LYD')->update(['rate_to_lyd' => '2.00000000']);

        $this->actingAs($actor)
            ->put(route('admin.exchange-rates.update'), [
                'usd_rate_to_lyd' => 6.50,
            ])
            ->assertRedirect(route('admin.exchange-rates.index'));

        $this->assertSame(
            '1.00000000',
            (string) ExchangeRate::query()->where('currency_code', 'LYD')->value('rate_to_lyd')
        );
    }

    public function test_updating_exchange_rate_notifies_customers_in_app_and_push_without_queue(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'is_active' => true,
        ]);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_ADMIN]);

        $this->actingAs($actor)
            ->put(route('admin.exchange-rates.update'), [
                'usd_rate_to_lyd' => 6.60,
            ])
            ->assertRedirect(route('admin.exchange-rates.index'));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $customer->id,
            'template_code' => 'EXCHANGE_RATE_UPDATED',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'template_code' => 'EXCHANGE_RATE_UPDATED',
            'channel' => NotificationChannels::IN_APP,
            'status' => NotificationLog::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'template_code' => 'EXCHANGE_RATE_UPDATED',
            'channel' => NotificationChannels::PUSH,
        ]);

        $this->assertDatabaseMissing('jobs', [
            'queue' => 'notifications-dispatch',
        ]);
        $this->assertDatabaseMissing('jobs', [
            'queue' => 'notifications-push',
        ]);
    }

    public function test_fetching_rates_does_not_dispatch_exchange_rate_updated_event(): void
    {
        Event::fake([ExchangeRateUpdated::class]);

        $this->getJson('/api/v1/currency/rates')->assertOk();
        $this->postJson('/api/v1/currency/convert', [
            'amount' => 1,
            'from' => 'USD',
            'to' => 'LYD',
        ])->assertOk();

        Event::assertNotDispatched(ExchangeRateUpdated::class);
    }

    public function test_conversion_service_formula(): void
    {
        $service = app(ExchangeRateService::class);

        $result = $service->convert(100, 'EUR', 'USD');

        $this->assertSame('EUR', $result['from']);
        $this->assertSame('USD', $result['to']);
        $this->assertEqualsWithDelta(116.03622802, $result['converted_amount'], 0.00000001);
        $this->assertEqualsWithDelta(1.16036228, $result['rate'], 0.00000001);
    }
}
