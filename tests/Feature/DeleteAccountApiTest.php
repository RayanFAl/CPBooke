<?php

namespace Tests\Feature;

use App\Jobs\ProcessScheduledAccountDeletionsJob;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\Favorite;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\User;
use App\Modules\Api\User\Services\CustomerAccountDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_delete_account_immediately_with_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => null,
            'phone' => '0911111111',
            'email' => 'delete-me@example.test',
        ]);

        Favorite::query()->create([
            'user_id' => $user->id,
            'type' => Favorite::TYPE_HOTEL,
            'item_key' => 'hotel:sample-1',
            'status' => Favorite::STATUS_ACTIVE,
            'snapshot' => ['name' => 'Sample Hotel'],
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'password' => 'password',
            'mode' => 'immediate',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account deleted successfully.')
            ->assertJsonPath('data.mode', 'immediate');

        $tombstone = User::withTrashed()->find($user->id);

        $this->assertNotNull($tombstone);
        $this->assertTrue($tombstone->trashed());
        $this->assertFalse($tombstone->is_active);
        $this->assertNull($tombstone->phone);
        $this->assertStringContainsString('@account.invalid', (string) $tombstone->email);
        $this->assertSame(0, Favorite::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }

    public function test_customer_can_schedule_account_deletion_for_60_days(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => null,
            'email' => 'schedule-me@example.test',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'password' => 'password',
            'mode' => 'scheduled',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', 'scheduled')
            ->assertJsonPath('data.grace_period_days', 60)
            ->assertJsonPath('data.can_cancel', true);

        $user->refresh();

        $this->assertFalse($user->trashed());
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->deletion_scheduled_at);
        $this->assertNotNull($user->deletion_due_at);
        $this->assertTrue($user->deletion_due_at->equalTo(
            $user->deletion_scheduled_at->copy()->addDays(60)
        ));
        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }

    public function test_scheduled_account_cannot_login_and_can_cancel_deletion(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => null,
            'email' => 'pending-delete@example.test',
            'phone' => '0955555555',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'password' => 'password',
            'mode' => 'scheduled',
        ])->assertOk();

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'pending-delete@example.test',
            'password' => 'password',
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'account_pending_deletion')
            ->assertJsonPath('errors.can_cancel', true);

        $this->postJson('/api/v1/users/account/deletion/cancel', [
            'login' => 'pending-delete@example.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', 'cancelled');

        $user->refresh();

        $this->assertTrue($user->is_active);
        $this->assertNull($user->deletion_scheduled_at);
        $this->assertNull($user->deletion_due_at);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'pending-delete@example.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_due_scheduled_deletions_are_processed_by_job(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => null,
            'is_active' => false,
            'deletion_scheduled_at' => now()->subDays(60),
            'deletion_due_at' => now()->subMinute(),
            'email' => 'due-delete@example.test',
        ]);

        (new ProcessScheduledAccountDeletionsJob)->handle(
            app(CustomerAccountDeletionService::class),
            app(\App\Modules\Monitoring\Services\ApplicationEventRecorder::class),
        );

        $tombstone = User::withTrashed()->findOrFail($user->id);

        $this->assertTrue($tombstone->trashed());
        $this->assertNotNull($tombstone->account_deleted_at);
        $this->assertNull($tombstone->deletion_due_at);
    }

    public function test_customer_account_deletion_is_available_on_customer_route(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/customer/account', [
            'password' => 'password',
            'mode' => 'immediate',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_customer_can_register_again_with_same_phone_after_deletion(): void
    {
        Notification::fake();

        $phone = '0922222222';
        $email = 'reuse@example.test';

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'phone' => $phone,
            'email' => $email,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'password' => 'password',
            'mode' => 'immediate',
        ])->assertOk();

        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New Account',
            'email' => 'new-'.$email,
            'phone' => $phone,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated();

        $newUserId = (int) $response->json('data.user.id');

        $this->assertNotSame($user->id, $newUserId);
        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'phone' => $phone,
            'deleted_at' => null,
        ]);
        $this->assertSame(0, Favorite::query()->where('user_id', $newUserId)->count());
    }

    public function test_customer_can_register_again_with_same_email_after_deletion(): void
    {
        Notification::fake();

        $phone = '0933333333';
        $email = 'email-reuse@example.test';

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'phone' => $phone,
            'email' => $email,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'password' => 'password',
            'mode' => 'immediate',
        ])->assertOk();

        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Fresh Email User',
            'email' => $email,
            'phone' => '0944444444',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated();

        $newUserId = (int) $response->json('data.user.id');
        $this->assertNotSame($user->id, $newUserId);
        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'email' => $email,
            'deleted_at' => null,
        ]);
    }

    public function test_deleted_account_cannot_access_api_with_old_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => null,
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/users/account', [
                'password' => 'password',
                'mode' => 'immediate',
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/profile')
            ->assertUnauthorized();
    }

    public function test_orders_and_wallet_transactions_are_preserved_after_deletion(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => null,
        ]);

        $order = Order::query()->create([
            'customer_id' => $user->id,
            'provider_name' => 'Delete Account Provider',
            'booking_reference' => 'BK-DEL-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['route' => 'TIP-BEN'],
            'currency' => 'LYD',
            'total_amount' => '500.00',
            'request_payload' => ['route' => 'TIP-BEN'],
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '500.00',
            'currency' => 'LYD',
            'source' => 'delete_account_test',
        ]);

        $wallet = CustomerWallet::query()->create([
            'user_id' => $user->id,
            'wallet_number' => 'CW-DEL-001',
            'currency' => 'LYD',
            'balance' => '0.00',
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        CustomerWalletTransaction::query()->create([
            'customer_wallet_id' => $wallet->id,
            'type' => CustomerWalletTransaction::TYPE_ADMIN_CREDIT,
            'amount' => '50.00',
            'currency' => 'LYD',
            'balance_before' => '0.00',
            'balance_after' => '50.00',
            'reference_type' => CustomerWalletTransaction::REFERENCE_MANUAL,
            'reference_id' => 'delete-account-test-credit',
            'description' => 'Historical credit',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'password' => 'password',
            'mode' => 'immediate',
        ])->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => $user->id,
        ]);
        $this->assertDatabaseHas('financial_transactions', [
            'order_id' => $order->id,
            'source' => 'delete_account_test',
        ]);
        $this->assertDatabaseHas('customer_wallet_transactions', [
            'customer_wallet_id' => $wallet->id,
            'amount' => '50.00',
        ]);
    }

    public function test_customer_cannot_delete_account_with_wallet_balance(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => null,
        ]);

        CustomerWallet::query()->create([
            'user_id' => $user->id,
            'wallet_number' => 'CW-DEL-002',
            'currency' => 'LYD',
            'balance' => '25.00',
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'password' => 'password',
            'mode' => 'immediate',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNull(User::withTrashed()->find($user->id)?->deleted_at);
    }

    public function test_customer_cannot_delete_account_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'password' => 'wrong-password',
            'mode' => 'immediate',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNull($user->fresh()?->deleted_at);
    }

    public function test_google_customer_can_delete_account_with_confirmation(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => 'google-sub-123',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'confirmation' => 'DELETE',
            'mode' => 'immediate',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(User::withTrashed()->find($user->id)?->trashed() ?? false);
    }

    public function test_admin_cannot_delete_account_via_mobile_endpoint(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
            'google_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'password' => 'password',
            'mode' => 'immediate',
        ])
            ->assertStatus(422);

        $this->assertNull($user->fresh()?->deleted_at);
    }

    public function test_delete_account_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/users/account', [
            'password' => 'password',
            'mode' => 'immediate',
        ])->assertUnauthorized();
    }

    public function test_delete_account_requires_mode(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'password' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_delete_account_service_is_idempotent_for_already_deleted_customer(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => null,
        ]);

        $service = app(CustomerAccountDeletionService::class);

        $service->delete($user);
        $service->delete(User::withTrashed()->findOrFail($user->id));

        $this->assertTrue(User::withTrashed()->findOrFail($user->id)->trashed());
    }
}
