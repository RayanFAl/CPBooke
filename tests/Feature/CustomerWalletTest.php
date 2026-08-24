<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientCustomerWalletBalanceException;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\Order;
use App\Models\User;
use App\Modules\CustomerWallets\Services\CustomerWalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customer_wallets.test_mode' => true,
            'customer_wallets.test_top_up_max' => 1000,
        ]);
    }

    public function test_customer_can_get_wallet_and_transactions(): void
    {
        $customer = $this->makeCustomer();
        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.currency', 'LYD')
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.test_mode_enabled', true);

        $this->postJson('/api/v1/wallet/test/top-up', ['amount' => 75])
            ->assertOk()
            ->assertJsonPath('data.wallet.balance', '75.00');

        $this->getJson('/api/v1/wallet/transactions')
            ->assertOk()
            ->assertJsonPath('data.wallet.balance', '75.00')
            ->assertJsonPath('data.transactions.data.0.type', CustomerWalletTransaction::TYPE_CREDIT)
            ->assertJsonPath('data.transactions.data.0.amount', '75.00');
    }

    public function test_admin_can_credit_customer_wallet_and_record_balance_before_after(): void
    {
        $admin = $this->makeAdmin('super_admin');
        $customer = $this->makeCustomer();

        $wallet = CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000001-TEST01',
            'currency' => 'LYD',
            'balance' => 0,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->post("/admin/customer-wallets/{$wallet->id}/credit", [
                'amount' => 1000,
                'note' => 'Initial test credit',
            ])
            ->assertRedirect(route('admin.customer-wallets.show', $wallet));

        $wallet->refresh();

        $this->assertSame('1000.00', (string) $wallet->balance);
        $this->assertDatabaseHas('customer_wallet_transactions', [
            'customer_wallet_id' => $wallet->id,
            'type' => CustomerWalletTransaction::TYPE_ADMIN_CREDIT,
            'amount' => '1000.00',
            'balance_before' => '0.00',
            'balance_after' => '1000.00',
            'created_by' => $admin->id,
        ]);
    }

    public function test_wallet_payment_and_refund_restore_balance(): void
    {
        $customer = $this->makeCustomer();
        $wallet = CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000002-TEST02',
            'currency' => 'LYD',
            'balance' => 1000,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'BookNow',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 550,
            'request_payload' => ['test' => true],
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/wallet/pay-order', [
            'order_id' => $order->id,
        ])->assertOk()
            ->assertJsonPath('data.wallet.balance', '450.00');

        $wallet->refresh();
        $this->assertSame('450.00', (string) $wallet->balance);
        $this->assertSame(Order::PAYMENT_METHOD_WALLET, $order->refresh()->payment_method);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->payment_status);

        app(CustomerWalletService::class)->refundForOrder($order, 550);

        $wallet->refresh();
        $this->assertSame('1000.00', (string) $wallet->balance);
        $this->assertDatabaseHas('customer_wallet_transactions', [
            'customer_wallet_id' => $wallet->id,
            'type' => CustomerWalletTransaction::TYPE_REFUND,
            'balance_before' => '450.00',
            'balance_after' => '1000.00',
        ]);
    }

    public function test_wallet_payment_then_top_up_then_refund_ends_with_1100(): void
    {
        $customer = $this->makeCustomer();
        $wallet = CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000006-TEST06',
            'currency' => 'LYD',
            'balance' => 1000,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        $order = $this->makeOrder($customer, 550);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/wallet/pay-order', [
            'order_id' => $order->id,
        ])->assertOk()
            ->assertJsonPath('data.wallet.balance', '450.00');

        $this->postJson('/api/v1/wallet/test/top-up', [
            'amount' => 100,
        ])->assertOk()
            ->assertJsonPath('data.wallet.balance', '550.00');

        app(CustomerWalletService::class)->refundForOrder($order->refresh(), 550);

        $this->assertSame('1100.00', (string) $wallet->refresh()->balance);
    }

    public function test_wallet_payment_is_idempotent_for_same_order(): void
    {
        $customer = $this->makeCustomer();
        $wallet = CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000007-TEST07',
            'currency' => 'LYD',
            'balance' => 1000,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        $order = $this->makeOrder($customer, 550);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/wallet/pay-order', [
            'order_id' => $order->id,
        ])->assertOk();

        $this->postJson('/api/v1/wallet/pay-order', [
            'order_id' => $order->id,
        ])->assertOk();

        $wallet->refresh();
        $this->assertSame('450.00', (string) $wallet->balance);
        $this->assertSame(
            1,
            CustomerWalletTransaction::query()
                ->where('customer_wallet_id', $wallet->id)
                ->where('order_id', $order->id)
                ->where('type', CustomerWalletTransaction::TYPE_BOOKING)
                ->count(),
        );
    }

    public function test_refund_for_same_order_cannot_be_applied_twice(): void
    {
        $customer = $this->makeCustomer();
        $wallet = CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000008-TEST08',
            'currency' => 'LYD',
            'balance' => 1000,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        $order = $this->makeOrder($customer, 550);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/wallet/pay-order', [
            'order_id' => $order->id,
        ])->assertOk();

        app(CustomerWalletService::class)->refundForOrder($order->refresh(), 550, null, [
            'reference_id' => 'refund:order:'.$order->id.':1',
        ]);

        $this->assertSame('1000.00', (string) $wallet->refresh()->balance);

        $this->expectException(ValidationException::class);

        app(CustomerWalletService::class)->refundForOrder($order->refresh(), 550, null, [
            'reference_id' => 'refund:order:'.$order->id.':2',
        ]);
    }

    public function test_wallet_payment_rejects_insufficient_balance(): void
    {
        $customer = $this->makeCustomer();
        CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000003-TEST03',
            'currency' => 'LYD',
            'balance' => 100,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'BookNow',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 550,
            'request_payload' => ['test' => true],
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/wallet/pay-order', [
            'order_id' => $order->id,
        ])->assertStatus(422)
            ->assertJsonPath('code', 'insufficient_wallet_balance');
    }

    public function test_test_top_up_is_blocked_when_test_mode_disabled(): void
    {
        config(['customer_wallets.test_mode' => false]);

        $customer = $this->makeCustomer();
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/wallet/test/top-up', [
            'amount' => 100,
        ])->assertStatus(403)
            ->assertJsonPath('code', 'wallet_test_disabled');
    }

    public function test_test_top_up_credits_wallet_when_enabled(): void
    {
        $customer = $this->makeCustomer();
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/wallet/test/top-up', [
            'amount' => 100,
        ])->assertOk()
            ->assertJsonPath('data.wallet.balance', '100.00');

        $this->assertDatabaseHas('customer_wallets', [
            'user_id' => $customer->id,
            'balance' => '100.00',
        ]);
    }

    public function test_test_top_up_allows_1000_and_rejects_1001(): void
    {
        $customer = $this->makeCustomer();
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/wallet/test/top-up', [
            'amount' => 1000,
        ])->assertOk();

        $this->postJson('/api/v1/wallet/test/top-up', [
            'amount' => 1001,
        ])->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Test top-up cannot exceed 1000 LYD.');
    }

    public function test_frozen_wallet_can_pay_after_unfreeze(): void
    {
        $customer = $this->makeCustomer();
        $wallet = CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000009-TEST09',
            'currency' => 'LYD',
            'balance' => 1000,
            'status' => CustomerWallet::STATUS_FROZEN,
        ]);

        $order = $this->makeOrder($customer, 550);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/wallet/pay-order', [
            'order_id' => $order->id,
        ])->assertStatus(422);

        app(CustomerWalletService::class)->unfreeze($wallet);

        $this->postJson('/api/v1/wallet/pay-order', [
            'order_id' => $order->id,
        ])->assertOk()
            ->assertJsonPath('data.wallet.balance', '450.00');
    }

    public function test_frozen_wallet_rejects_payment(): void
    {
        $customer = $this->makeCustomer();
        CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000004-TEST04',
            'currency' => 'LYD',
            'balance' => 1000,
            'status' => CustomerWallet::STATUS_FROZEN,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'BookNow',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 550,
            'request_payload' => ['test' => true],
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/wallet/pay-order', [
            'order_id' => $order->id,
        ])->assertStatus(422)
            ->assertJsonPath('errors.wallet.0', 'This wallet is frozen.');
    }

    public function test_service_debit_throws_insufficient_balance_exception(): void
    {
        $customer = $this->makeCustomer();
        $wallet = CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000005-TEST05',
            'currency' => 'LYD',
            'balance' => 50,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        $this->expectException(InsufficientCustomerWalletBalanceException::class);

        app(CustomerWalletService::class)->adminDebit($wallet, 100);
    }

    public function test_pay_order_also_debits_provider_wallet(): void
    {
        $customer = $this->makeCustomer();
        CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000010-TEST10',
            'currency' => 'LYD',
            'balance' => 1000,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        $provider = \App\Models\Provider::query()->create([
            'name' => 'BookNow',
            'key' => \App\Models\Provider::KEY_BOOKNOW,
            'status' => \App\Models\Provider::STATUS_ACTIVE,
        ]);

        $providerWallet = \App\Models\ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => 'production',
            'balance' => 2000,
            'allow_negative' => true,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
            'provider_name' => 'BookNow',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 550,
            'selling_price' => 550,
            'supplier_cost' => 500,
            'request_payload' => ['product_type' => 'flight', 'test' => true],
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/wallet/pay-order', [
            'order_id' => $order->id,
        ])->assertOk()
            ->assertJsonPath('data.wallet.balance', '450.00');

        $this->assertSame('1500.00', (string) $providerWallet->refresh()->balance);
        $this->assertDatabaseHas('provider_wallet_transactions', [
            'provider_wallet_id' => $providerWallet->id,
            'type' => \App\Models\ProviderWalletTransaction::TYPE_DEBIT,
            'order_id' => $order->id,
            'amount' => '500.00',
        ]);
        $this->assertSame(Order::PAYMENT_METHOD_WALLET, $order->refresh()->payment_method);
    }

    public function test_sync_flight_with_wallet_debits_customer_and_provider(): void
    {
        $customer = $this->makeCustomer();
        CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000011-TEST11',
            'currency' => 'LYD',
            'balance' => 1000,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        $provider = \App\Models\Provider::query()->create([
            'name' => 'BookNow',
            'key' => \App\Models\Provider::KEY_BOOKNOW,
            'status' => \App\Models\Provider::STATUS_ACTIVE,
            'commission_rate' => '10.00',
        ]);

        $providerWallet = \App\Models\ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => 'production',
            'balance' => 2000,
            'allow_negative' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer);

        $payload = [
            'source' => 'mobile_app',
            'product_type' => 'flight',
            'status' => 'confirmed',
            'currency' => 'LYD',
            'grand_total' => 590.00,
            'base_amount' => 531.00,
            'provider_booking' => [
                'booking_id' => 'wallet-sync-booking-001',
                'order_number' => 'WFQWALLET1',
                'pnr' => 'WLTAAA',
                'provider_id' => 12,
                'provider_name' => 'Buraq Air',
            ],
            'contact' => [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $customer->email,
                'phone' => '+218911111111',
            ],
            'passengers' => [
                [
                    'type' => 'adult',
                    'title' => 'Mr',
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'dob' => '1990-01-01',
                    'gender' => 'M',
                    'nationality' => 'LY',
                    'passport_number' => 'P1234567',
                    'passport_expiry' => '2030-01-01',
                    'passport_issue_country' => 'LY',
                ],
            ],
            'items' => [
                [
                    'type' => 'flight',
                    'product_type' => 'ticket',
                    'product_subtype' => 'oneway',
                    'provider_reference' => 'WLTAAA',
                    'total' => 590.00,
                    'currency' => 'LYD',
                    'item_details' => [
                        'pnr' => 'WLTAAA',
                        'airline_code' => 'BM',
                        'airline_name' => 'Buraq Air',
                        'segments' => [],
                        'passengers' => [],
                    ],
                ],
            ],
            'payment' => [
                'status' => 'paid',
                'method' => 'wallet',
                'amount' => 590.00,
                'currency' => 'LYD',
            ],
        ];

        $this->postJson('/api/v1/orders/sync-flight', $payload)
            ->assertCreated();

        $order = Order::query()->where('external_booking_id', 'wallet-sync-booking-001')->firstOrFail();

        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->payment_status);
        $this->assertSame(Order::PAYMENT_METHOD_WALLET, $order->payment_method);
        $this->assertSame('410.00', (string) CustomerWallet::query()->where('user_id', $customer->id)->value('balance'));
        $this->assertSame('1469.00', (string) $providerWallet->refresh()->balance);
        $this->assertDatabaseHas('customer_wallet_transactions', [
            'order_id' => $order->id,
            'type' => CustomerWalletTransaction::TYPE_BOOKING,
            'amount' => '590.00',
        ]);
    }

    public function test_sync_flight_with_wallet_rejects_insufficient_customer_balance(): void
    {
        $customer = $this->makeCustomer();
        CustomerWallet::query()->create([
            'user_id' => $customer->id,
            'wallet_number' => 'WLT-000012-TEST12',
            'currency' => 'LYD',
            'balance' => 10,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($customer);

        $payload = [
            'source' => 'mobile_app',
            'product_type' => 'flight',
            'status' => 'confirmed',
            'currency' => 'LYD',
            'grand_total' => 590.00,
            'provider_booking' => [
                'booking_id' => 'wallet-sync-fail-001',
                'order_number' => 'WFQFAIL1',
                'pnr' => 'FAIL01',
                'provider_id' => 12,
                'provider_name' => 'Buraq Air',
            ],
            'contact' => [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $customer->email,
                'phone' => '+218911111111',
            ],
            'passengers' => [
                [
                    'type' => 'adult',
                    'title' => 'Mr',
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'dob' => '1990-01-01',
                    'gender' => 'M',
                    'nationality' => 'LY',
                    'passport_number' => 'P1234567',
                    'passport_expiry' => '2030-01-01',
                    'passport_issue_country' => 'LY',
                ],
            ],
            'items' => [
                [
                    'type' => 'flight',
                    'product_type' => 'ticket',
                    'product_subtype' => 'oneway',
                    'provider_reference' => 'FAIL01',
                    'total' => 590.00,
                    'currency' => 'LYD',
                    'item_details' => [
                        'pnr' => 'FAIL01',
                        'segments' => [],
                        'passengers' => [],
                    ],
                ],
            ],
            'payment' => [
                'status' => 'paid',
                'method' => 'wallet',
                'amount' => 590.00,
                'currency' => 'LYD',
            ],
        ];

        $this->postJson('/api/v1/orders/sync-flight', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'insufficient_wallet_balance');

        $this->assertDatabaseMissing('orders', [
            'external_booking_id' => 'wallet-sync-fail-001',
        ]);
        $this->assertSame('10.00', (string) CustomerWallet::query()->where('user_id', $customer->id)->value('balance'));
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

    private function makeCustomer(): User
    {
        return User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
    }

    private function makeOrder(User $customer, float $amount): Order
    {
        return Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'BookNow',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => $amount,
            'request_payload' => ['test' => true],
        ]);
    }
}
