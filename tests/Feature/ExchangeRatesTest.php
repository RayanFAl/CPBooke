<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExchangeRate;
use App\Models\NotificationLog;
use App\Models\User;
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

    public function test_api_returns_buy_sell_and_mid_for_supported_currencies(): void
    {
        $response = $this->getJson('/api/v1/currency/rates');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.base_currency', 'LYD')
            ->assertJsonPath('data.rates.LYD.buy', 1)
            ->assertJsonPath('data.rates.LYD.sell', 1)
            ->assertJsonPath('data.rates.LYD.mid', 1)
            ->assertJsonPath('data.rates.USD.buy', 9.35)
            ->assertJsonPath('data.rates.USD.sell', 9.42)
            ->assertJsonPath('data.rates.EUR.buy', 10.85)
            ->assertJsonPath('data.rates.EUR.sell', 10.93);

        $this->assertSame(['LYD', 'USD', 'EUR'], array_keys($response->json('data.rates')));
        $this->assertEqualsWithDelta(9.385, (float) $response->json('data.rates.USD.mid'), 0.00000001);
    }

    public function test_api_converts_with_mid_by_default(): void
    {
        $response = $this->postJson('/api/v1/currency/convert', [
            'amount' => 100,
            'from' => 'EUR',
            'to' => 'USD',
        ]);

        $usdMid = (9.35 + 9.42) / 2;
        $eurMid = (10.85 + 10.93) / 2;
        $expected = 100 * $eurMid / $usdMid;

        $response->assertOk()
            ->assertJsonPath('data.side', 'mid')
            ->assertJsonPath('data.from', 'EUR')
            ->assertJsonPath('data.to', 'USD');

        $this->assertEqualsWithDelta($expected, (float) $response->json('data.converted_amount'), 0.00000001);
    }

    public function test_api_auto_side_uses_buy_of_source_and_sell_of_target(): void
    {
        // 100 EUR → USD auto = 100 * buy(EUR) / sell(USD) = 100 * 10.85 / 9.42
        $response = $this->postJson('/api/v1/currency/convert', [
            'amount' => 100,
            'from' => 'EUR',
            'to' => 'USD',
            'side' => 'auto',
        ]);

        $response->assertOk()->assertJsonPath('data.side', 'auto');
        $this->assertEqualsWithDelta(115.18046709, (float) $response->json('data.converted_amount'), 0.00000001);
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

    public function test_admin_can_update_buy_and_sell_rates(): void
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
                'usd_buy_rate_to_lyd' => 9.30,
                'usd_sell_rate_to_lyd' => 9.50,
                'eur_buy_rate_to_lyd' => 10.80,
                'eur_sell_rate_to_lyd' => 11.00,
            ])
            ->assertRedirect(route('admin.exchange-rates.index'));

        $this->assertDatabaseHas('exchange_rates', [
            'currency_code' => 'USD',
            'buy_rate_to_lyd' => '9.30000000',
            'sell_rate_to_lyd' => '9.50000000',
        ]);
        $this->assertDatabaseHas('exchange_rates', [
            'currency_code' => 'EUR',
            'buy_rate_to_lyd' => '10.80000000',
            'sell_rate_to_lyd' => '11.00000000',
        ]);
        $this->assertDatabaseHas('exchange_rates', [
            'currency_code' => 'LYD',
            'buy_rate_to_lyd' => '1.00000000',
            'sell_rate_to_lyd' => '1.00000000',
        ]);

        $this->assertNull(Cache::get(ExchangeRateService::CACHE_KEY));
        Event::assertDispatched(ExchangeRateUpdated::class, 2);

        $this->assertDatabaseHas('audit_logs', [
            'module' => AuditLog::MODULE_EXCHANGE_RATES,
            'action' => 'exchange_rate.updated',
            'entity_type' => AuditLog::ENTITY_EXCHANGE_RATE,
            'status' => AuditLog::STATUS_SUCCESS,
        ]);
    }

    public function test_admin_rejects_sell_lower_than_buy(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_ADMIN]);

        $this->actingAs($actor)
            ->from(route('admin.exchange-rates.index'))
            ->put(route('admin.exchange-rates.update'), [
                'usd_buy_rate_to_lyd' => 9.50,
                'usd_sell_rate_to_lyd' => 9.20,
            ])
            ->assertRedirect(route('admin.exchange-rates.index'))
            ->assertSessionHasErrors('usd_sell_rate_to_lyd');
    }

    public function test_updating_exchange_rate_notifies_customers(): void
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
                'usd_buy_rate_to_lyd' => 9.30,
                'usd_sell_rate_to_lyd' => 9.50,
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
    }

    public function test_fetching_rates_does_not_dispatch_update_event(): void
    {
        Event::fake([ExchangeRateUpdated::class]);

        $this->getJson('/api/v1/currency/rates')->assertOk();
        $this->postJson('/api/v1/currency/convert', [
            'amount' => 1,
            'from' => 'USD',
            'to' => 'LYD',
            'side' => 'sell',
        ])->assertOk();

        Event::assertNotDispatched(ExchangeRateUpdated::class);
    }
}
