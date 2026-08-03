<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use App\Modules\Wallets\Services\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_provider_wallet_and_deposit(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => 'booknow',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->post('/admin/provider-wallets', [
                'provider_id' => $provider->id,
                'currency' => 'LYD',
                'environment' => 'production',
                'low_balance_threshold' => 1000,
                'allow_negative' => true,
            ])
            ->assertRedirect();

        $wallet = ProviderWallet::query()->firstOrFail();

        $this->assertSame($provider->id, $wallet->provider_id);
        $this->assertSame('0.00', (string) $wallet->balance);
        $this->assertSame('production', $wallet->environment);

        $this->actingAs($admin)
            ->post("/admin/provider-wallets/{$wallet->id}/deposit", [
                'amount' => 5000,
                'note' => 'Wire transfer ref 123',
            ])
            ->assertRedirect(route('admin.provider-wallets.show', $wallet));

        $wallet->refresh();

        $this->assertSame('5000.00', (string) $wallet->balance);
        $this->assertDatabaseHas('provider_wallet_transactions', [
            'provider_wallet_id' => $wallet->id,
            'type' => ProviderWalletTransaction::TYPE_DEPOSIT,
            'amount' => '5000.00',
            'balance_after' => '5000.00',
            'reference_type' => ProviderWalletTransaction::REFERENCE_MANUAL,
            'created_by' => $admin->id,
        ]);
    }

    public function test_finance_manager_deposit_creates_pending_approval_without_changing_balance(): void
    {
        $viewer = $this->makeAdmin('finance_manager');
        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => 'booknow',
            'status' => Provider::STATUS_ACTIVE,
        ]);
        $wallet = ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => 'production',
            'balance' => 100,
            'allow_negative' => true,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/admin/provider-wallets')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/provider-wallets/pages/Index', false)
                ->where('can_manage', true)
            );

        $this->actingAs($viewer)
            ->post("/admin/provider-wallets/{$wallet->id}/deposit", [
                'amount' => 50,
            ])
            ->assertRedirect(route('admin.provider-wallets.show', $wallet));

        $this->assertDatabaseHas('approvals', [
            'type' => 'wallet_deposit',
            'entity_type' => 'wallet',
            'entity_id' => $wallet->id,
            'status' => 'pending',
        ]);

        $this->assertSame('100.00', (string) $wallet->refresh()->balance);
    }

    public function test_paid_sync_flight_debits_via_generic_wallet_service_idempotently(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => Provider::KEY_BOOKNOW,
            'status' => Provider::STATUS_ACTIVE,
            'commission_rate' => '10.00',
            'settlement_cycle' => Provider::SETTLEMENT_MONTHLY,
            'default_currency' => 'LYD',
            'integration_status' => Provider::INTEGRATION_LIVE,
        ]);

        $wallet = ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => 'production',
            'balance' => 1000,
            'allow_negative' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer);

        $payload = $this->booknowOrderPayload();

        $this->postJson('/api/v1/orders/sync-flight', $payload)
            ->assertCreated();

        $order = Order::query()->where('external_booking_id', '01ktgspkyr6ma0fjqjc7vexyry')->firstOrFail();

        $this->assertSame($provider->id, $order->provider_id);
        $this->assertSame('590.00', (string) $order->selling_price);
        $this->assertSame('531.00', (string) $order->supplier_cost);
        $this->assertSame('59.00', (string) $order->commission_amount);
        $this->assertSame('59.00', (string) $order->profit_amount);

        $wallet->refresh();
        $this->assertSame('469.00', (string) $wallet->balance);

        $this->assertDatabaseHas('provider_wallet_transactions', [
            'provider_wallet_id' => $wallet->id,
            'type' => ProviderWalletTransaction::TYPE_DEBIT,
            'amount' => '531.00',
            'order_id' => $order->id,
            'reference_type' => ProviderWalletTransaction::REFERENCE_FLIGHT_BOOKING,
            'reference_id' => (string) $order->id,
        ]);

        $this->postJson('/api/v1/orders/sync-flight', $payload)
            ->assertOk();

        $wallet->refresh();
        $this->assertSame('469.00', (string) $wallet->balance);
        $this->assertSame(1, ProviderWalletTransaction::query()->where('type', ProviderWalletTransaction::TYPE_DEBIT)->count());
        $this->assertTrue($admin->hasPermissionTo('provider-wallets.manage'));
    }

    public function test_debit_allows_negative_when_policy_enabled(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/orders/sync-flight', $this->booknowOrderPayload())
            ->assertCreated();

        $provider = Provider::query()->where('key', Provider::KEY_BOOKNOW)->firstOrFail();
        $wallet = ProviderWallet::query()
            ->where('provider_id', $provider->id)
            ->where('currency', 'LYD')
            ->firstOrFail();

        $this->assertSame('-590.00', (string) $wallet->balance);
        $this->assertTrue($wallet->allow_negative);
        $this->assertTrue($wallet->isLowBalance());
    }

    public function test_debit_rejects_when_allow_negative_is_false(): void
    {
        $provider = Provider::query()->create([
            'name' => 'Duffel',
            'key' => 'duffel',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        $wallet = ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'USD',
            'environment' => 'production',
            'balance' => 100,
            'allow_negative' => false,
            'is_active' => true,
        ]);

        $this->expectException(InsufficientWalletBalanceException::class);

        app(WalletService::class)->debit(
            providerId: $provider->id,
            amount: 250,
            currency: 'USD',
            referenceType: ProviderWalletTransaction::REFERENCE_FLIGHT_BOOKING,
            referenceId: 999,
            options: [
                'create_wallet_if_missing' => false,
            ],
        );

        $wallet->refresh();
        $this->assertSame('100.00', (string) $wallet->balance);
    }

    public function test_same_provider_can_have_multiple_currency_and_environment_wallets(): void
    {
        $provider = Provider::query()->create([
            'name' => 'Duffel',
            'key' => 'duffel',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        $service = app(WalletService::class);

        $usd = $service->createWallet([
            'provider_id' => $provider->id,
            'currency' => 'USD',
            'environment' => 'production',
        ]);

        $eur = $service->createWallet([
            'provider_id' => $provider->id,
            'currency' => 'EUR',
            'environment' => 'production',
        ]);

        $sandbox = $service->createWallet([
            'provider_id' => $provider->id,
            'currency' => 'USD',
            'environment' => 'sandbox',
        ]);

        $this->assertNotSame($usd->id, $eur->id);
        $this->assertNotSame($usd->id, $sandbox->id);
        $this->assertSame(3, ProviderWallet::query()->where('provider_id', $provider->id)->count());
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

    /**
     * @return array<string, mixed>
     */
    private function booknowOrderPayload(): array
    {
        return [
            'source' => 'mobile_app',
            'product_type' => 'flight',
            'status' => 'confirmed',
            'currency' => 'LYD',
            'grand_total' => 590.00,
            'provider_booking' => [
                'booking_id' => '01ktgspkyr6ma0fjqjc7vexyry',
                'order_number' => 'WFQ0001OZ',
                'pnr' => 'AAXKDO',
                'provider_id' => 12,
                'provider_name' => 'Buraq Air',
                'search_uuid' => 'search-uuid-123',
            ],
            'contact' => [
                'first_name' => 'RAYAN',
                'last_name' => 'FATHI',
                'email' => 'a.rayan@median.ly',
                'phone' => '+218943215277',
            ],
            'passengers' => [
                [
                    'type' => 'adult',
                    'title' => 'Mr',
                    'first_name' => 'RAYAN',
                    'last_name' => 'FATHI',
                    'dob' => '1998-05-10',
                    'gender' => 'M',
                    'nationality' => 'LY',
                    'passport_number' => 'AB1234567',
                    'passport_expiry' => '2030-01-01',
                    'passport_issue_country' => 'LY',
                ],
            ],
            'items' => [
                [
                    'type' => 'flight',
                    'product_type' => 'ticket',
                    'product_subtype' => 'oneway',
                    'provider_reference' => 'AAXKDO',
                    'total' => 590.00,
                    'currency' => 'LYD',
                    'item_details' => [
                        'pnr' => 'AAXKDO',
                        'airline_code' => 'BM',
                        'airline_name' => 'Buraq Air',
                        'segments' => [
                            [
                                'flight_number' => 'BM0400',
                                'departure_airport' => 'MJI',
                                'arrival_airport' => 'TUN',
                                'departure_time' => '2026-06-20 10:25:00',
                                'arrival_time' => '2026-06-20 10:35:00',
                                'duration' => 10,
                                'cabin_type' => 'Y',
                                'class' => 'S',
                            ],
                        ],
                        'passengers' => [],
                    ],
                ],
            ],
            'payment' => [
                'status' => 'paid',
                'method' => 'wallet',
                'method_code' => 1,
                'amount' => 590.00,
                'currency' => 'LYD',
                'transaction_id' => 'txn_123',
                'paid_at' => '2026-06-07T10:22:50Z',
            ],
            'metadata' => [
                'app_version' => '1.0.0',
                'platform' => 'android',
            ],
            'booking_flight_data' => [
                'departure_airport' => 'MJI',
                'arrival_airport' => 'TUN',
                'departure_time' => '2026-06-20 10:25:00',
                'segments' => [],
            ],
        ];
    }
}
