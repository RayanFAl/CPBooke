<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use App\Modules\Api\User\Services\ProfileOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChangePasswordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_request_change_password_otp(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => 'OldPassword123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password/otp/send');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonStructure([
                'data' => [
                    'expires_in_seconds',
                    'resend_after_seconds',
                    'channel',
                    'target',
                ],
            ]);

        Mail::assertSent(PasswordResetOtpMail::class);
    }

    public function test_authenticated_customer_can_change_password_with_otp(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => 'OldPassword123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/change-password/otp/send')->assertOk();

        $otp = app(ProfileOtpService::class)->debugOtpForTests(
            $user,
            ProfileOtpService::PURPOSE_PASSWORD_CHANGE,
        );

        $this->assertNotNull($otp);

        $response = $this->putJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'otp' => $otp,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_change_password_rejects_incorrect_current_password(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => 'OldPassword123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/change-password/otp/send')->assertOk();
        $otp = app(ProfileOtpService::class)->debugOtpForTests(
            $user,
            ProfileOtpService::PURPOSE_PASSWORD_CHANGE,
        );

        $response = $this->putJson('/api/v1/auth/change-password', [
            'current_password' => 'WrongPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'otp' => $otp,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('current_password');
    }

    public function test_change_password_rejects_missing_or_invalid_otp(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => 'OldPassword123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/change-password/otp/send')->assertOk();

        $response = $this->putJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'otp' => '000000',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('otp');
    }

    public function test_change_password_rejects_weak_new_password(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => 'OldPassword123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/change-password/otp/send')->assertOk();
        $otp = app(ProfileOtpService::class)->debugOtpForTests(
            $user,
            ProfileOtpService::PURPOSE_PASSWORD_CHANGE,
        );

        $response = $this->putJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'short',
            'password_confirmation' => 'short',
            'otp' => $otp,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('password');
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->putJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'otp' => '123456',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'unauthenticated');

        $this->postJson('/api/v1/auth/change-password/otp/send')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_change_password_keeps_current_session(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => 'OldPassword123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $token = $user->createToken('iPhone')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/change-password/otp/send')
            ->assertOk();

        $otp = app(ProfileOtpService::class)->debugOtpForTests(
            $user,
            ProfileOtpService::PURPOSE_PASSWORD_CHANGE,
        );

        $response = $this->withToken($token)->putJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'otp' => $otp,
        ]);

        $response->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email);
    }
}
