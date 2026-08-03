<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotificationDevice;
use App\Modules\Api\Auth\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PassengerSecurityNotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_get_and_update(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('data.push', true)
            ->assertJsonPath('data.login_alerts', true)
            ->assertJsonPath('data.promotional', false);

        $this->putJson('/api/v1/notifications/preferences', [
            'push' => false,
            'promotional' => true,
            'login_alerts' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.push', false)
            ->assertJsonPath('data.promotional', true)
            ->assertJsonPath('data.login_alerts', false);
    }

    public function test_device_can_be_disabled_and_deleted(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $token = 'fcm-real-token-abcdefghijklmnop';

        $this->postJson('/api/v1/notifications/devices', [
            'device_token' => $token,
            'platform' => 'android',
        ])->assertCreated();

        $this->patchJson('/api/v1/notifications/devices', [
            'device_token' => $token,
            'enabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        $this->assertFalse(
            UserNotificationDevice::query()->where('device_token', $token)->value('is_active')
        );

        $this->deleteJson('/api/v1/notifications/devices', [
            'device_token' => $token,
        ])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('user_notification_devices', [
            'device_token' => $token,
        ]);
    }

    public function test_session_can_be_revoked_by_id(): void
    {
        $user = User::factory()->create([
            'email' => 'sessions@example.com',
            'password' => 'Password123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $first = $this->postJson('/api/v1/auth/login', [
            'login' => 'sessions@example.com',
            'password' => 'Password123!',
            'device_name' => 'iPhone',
        ])->assertOk();

        $second = $this->postJson('/api/v1/auth/login', [
            'login' => 'sessions@example.com',
            'password' => 'Password123!',
            'device_name' => 'Android',
        ])->assertOk();

        $sessions = $this->withToken($first->json('data.access_token'))
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->json('data.sessions');

        $other = collect($sessions)->firstWhere('is_current', false);
        $this->assertNotNull($other);

        $this->withToken($first->json('data.access_token'))
            ->deleteJson('/api/v1/auth/sessions/'.$other['id'])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->app['auth']->forgetGuards();

        $this->withToken($second->json('data.access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_two_factor_login_challenge_and_verify(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => '2fa@example.com',
            'password' => 'Password123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $login = $this->postJson('/api/v1/auth/login', [
            'login' => '2fa@example.com',
            'password' => 'Password123!',
            'device_name' => 'iPhone',
        ])->assertOk();

        $login
            ->assertJsonPath('data.requires_2fa', true)
            ->assertJsonStructure(['data' => ['temp_token', 'type']]);

        $this->assertArrayNotHasKey('access_token', $login->json('data') ?? []);

        $code = $google2fa->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/2fa/verify', [
            'temp_token' => $login->json('data.temp_token'),
            'code' => $code,
        ])
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'expires_in', 'user'],
            ]);
    }

    public function test_two_factor_enable_confirm_flow(): void
    {
        $user = User::factory()->create([
            'password' => 'Password123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $enable = $this->postJson('/api/v1/auth/2fa/enable')
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_url', 'type']]);

        $secret = $enable->json('data.secret');
        $code = (new Google2FA())->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/2fa/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.enabled', true);

        $this->assertTrue(app(TwoFactorService::class)->isEnabled($user->fresh()));
    }
}
