<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChangePasswordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_change_password_rejects_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/change-password', [
            'current_password' => 'WrongPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('current_password');
    }

    public function test_change_password_rejects_weak_new_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'short',
            'password_confirmation' => 'short',
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
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_change_password_keeps_current_session(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $token = $user->createToken('iPhone')->plainTextToken;

        $response = $this->withToken($token)->putJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email);
    }
}
