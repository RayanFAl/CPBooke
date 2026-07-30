<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetOtpApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_otp_for_customer_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'customer@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.otp_expires_in_seconds', 600)
            ->assertJsonPath('data.resend_after_seconds', 60);

        Notification::assertSentTo($user, PasswordResetOtpNotification::class);
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.otp_expires_in_seconds', 600);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_throttles_resend(): void
    {
        Notification::fake();

        User::factory()->create([
            'email' => 'customer@example.com',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'customer@example.com',
        ])->assertOk();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'customer@example.com',
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'http_error');
    }

    public function test_full_otp_reset_flow_succeeds(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'OldPassword123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'is_active' => true,
        ]);

        $token = $user->createToken('iPhone')->plainTextToken;

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'customer@example.com',
        ])->assertOk();

        $otp = null;
        Notification::assertSentTo($user, PasswordResetOtpNotification::class, function (PasswordResetOtpNotification $notification) use (&$otp): bool {
            $otp = $notification->otp;

            return true;
        });

        $verify = $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => 'customer@example.com',
            'otp' => $otp,
        ]);

        $verify
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['reset_token', 'reset_token_expires_in_seconds']]);

        $resetToken = $verify->json('data.reset_token');

        $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => $resetToken,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password reset successfully.');

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_verify_reset_otp_rejects_invalid_code(): void
    {
        Notification::fake();

        User::factory()->create([
            'email' => 'customer@example.com',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'customer@example.com',
        ])->assertOk();

        $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => 'customer@example.com',
            'otp' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('otp');
    }

    public function test_reset_password_rejects_weak_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'customer@example.com',
        ])->assertOk();

        $otp = null;
        Notification::assertSentTo($user, PasswordResetOtpNotification::class, function (PasswordResetOtpNotification $notification) use (&$otp): bool {
            $otp = $notification->otp;

            return true;
        });

        $resetToken = $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => 'customer@example.com',
            'otp' => $otp,
        ])->json('data.reset_token');

        $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => $resetToken,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('password');
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => str_repeat('a', 64),
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('reset_token');
    }
}
