<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_delete_account_with_password(): void
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
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account deleted successfully.');

        $this->assertNull($user->fresh());
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
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNotNull($user->fresh());
    }

    public function test_google_customer_can_delete_account_with_confirmation(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'google_id' => 'google-sub-123',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/users/account', [
            'confirmation' => 'DELETE',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull($user->fresh());
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
        ])
            ->assertStatus(422);

        $this->assertNotNull($user->fresh());
    }

    public function test_delete_account_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/users/account', [
            'password' => 'password',
        ])->assertUnauthorized();
    }
}
