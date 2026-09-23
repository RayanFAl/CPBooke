<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Api\User\Services\ProfileOtpService;
use App\Notifications\ProfileOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAvatarAndVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_upload_and_delete(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->post('/api/v1/users/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);

        $this->getJson('/api/v1/users/profile')
            ->assertOk()
            ->assertJsonPath('data.user.avatar_url', $user->avatarUrl())
            ->assertJsonPath('data.user.email_verified', false)
            ->assertJsonPath('data.user.phone_verified', false);

        $this->deleteJson('/api/v1/users/profile/avatar')
            ->assertOk()
            ->assertJsonPath('data.user.avatar_url', null);

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_email_change_otp_flow(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/users/email/change-request', [
            'email' => 'new@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.channel', 'email');

        Notification::assertSentTo($user, ProfileOtpNotification::class);

        $otp = app(ProfileOtpService::class)->debugOtpForTests($user, ProfileOtpService::PURPOSE_EMAIL_CHANGE);
        $this->assertNotNull($otp);

        $this->postJson('/api/v1/users/email/verify', [
            'email' => 'new@example.com',
            'otp' => $otp,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'new@example.com')
            ->assertJsonPath('data.user.email_verified', true);

        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_email_and_phone_verification_flow(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'verify@example.com',
            'phone' => '+218911112233',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/users/verify/email/send')->assertOk();
        $emailOtp = app(ProfileOtpService::class)->debugOtpForTests($user, ProfileOtpService::PURPOSE_EMAIL_VERIFY);
        $this->assertNotNull($emailOtp);

        $this->postJson('/api/v1/users/verify/email/confirm', ['otp' => $emailOtp])
            ->assertOk()
            ->assertJsonPath('data.user.email_verified', true);

        $this->postJson('/api/v1/users/verify/phone/send')->assertOk();
        $phoneOtp = app(ProfileOtpService::class)->debugOtpForTests($user, ProfileOtpService::PURPOSE_PHONE_VERIFY);
        $this->assertNotNull($phoneOtp);

        $this->postJson('/api/v1/users/verify/phone/confirm', ['otp' => $phoneOtp])
            ->assertOk()
            ->assertJsonPath('data.user.phone_verified', true);
    }

    public function test_phone_change_otp_flow(): void
    {
        $user = User::factory()->create([
            'phone' => '+218900000001',
            'phone_verified_at' => now(),
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/users/phone/change-request', [
            'phone' => '+218900000099',
        ])
            ->assertOk()
            ->assertJsonPath('data.channel', 'sms');

        $otp = app(ProfileOtpService::class)->debugOtpForTests($user, ProfileOtpService::PURPOSE_PHONE_CHANGE);
        $this->assertNotNull($otp);

        $this->postJson('/api/v1/users/phone/verify', [
            'phone' => '+218900000099',
            'otp' => $otp,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.phone', '+218900000099')
            ->assertJsonPath('data.user.phone_verified', true);

        $this->assertSame('+218900000099', $user->fresh()->phone);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_profile_update_rejects_direct_phone_change(): void
    {
        $user = User::factory()->create([
            'name' => 'Rayan',
            'phone' => '+218900000001',
            'phone_verified_at' => now(),
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/users/profile', [
            'name' => 'Rayan',
            'phone' => '+218900000099',
            'country' => 'LY',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'errors' => ['phone'],
            ]);

        $this->assertSame('+218900000001', $user->fresh()->phone);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_profile_update_accepts_name_without_changing_phone(): void
    {
        $user = User::factory()->create([
            'name' => 'Fathi Hammel',
            'full_name' => 'Fathi Hammel',
            'phone' => '+218900000010',
            'country' => 'LY',
            'phone_verified_at' => now(),
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/users/profile', [
            'name' => 'Fathi Updated',
            'phone' => '+218900000010',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Fathi Updated')
            ->assertJsonPath('data.user.phone', '+218900000010')
            ->assertJsonPath('data.user.country', 'LY')
            ->assertJsonPath('data.user.phone_verified', true);

        $fresh = $user->fresh();
        $this->assertSame('Fathi Updated', $fresh->name);
        $this->assertSame('+218900000010', $fresh->phone);
        $this->assertSame('LY', $fresh->country);
        $this->assertNotNull($fresh->phone_verified_at);
    }

    public function test_phone_change_request_returns_validation_error_for_taken_phone(): void
    {
        User::factory()->create([
            'phone' => '+21894321527',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $user = User::factory()->create([
            'name' => 'Fathi Hammel',
            'phone' => '+218900000011',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/users/phone/change-request', [
            'phone' => '+21894321527',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure([
                'errors' => ['phone'],
            ]);
    }
}
