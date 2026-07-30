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

    public function test_changing_phone_clears_phone_verification(): void
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
            ->assertOk()
            ->assertJsonPath('data.user.phone_verified', false);

        $this->assertNull($user->fresh()->phone_verified_at);
    }
}
