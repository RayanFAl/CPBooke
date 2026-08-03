<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_access_refresh_and_expiry(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'Password123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'customer@example.com',
            'password' => 'Password123!',
            'device_name' => 'iPhone',
            'remember_me' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expires_in', 3600)
            ->assertJsonPath('data.remember_me', false)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token',
                    'expires_in',
                    'expires_at',
                    'remember_me',
                    'token',
                    'user' => ['email'],
                ],
            ]);

        $this->assertSame(
            $response->json('data.access_token'),
            $response->json('data.token'),
        );
    }

    public function test_remember_me_extends_access_token_ttl(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'Password123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'customer@example.com',
            'password' => 'Password123!',
            'remember_me' => true,
            'device_name' => 'Android',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.remember_me', true)
            ->assertJsonPath('data.expires_in', 604800);
    }

    public function test_refresh_rotates_tokens(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'Password123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'login' => 'customer@example.com',
            'password' => 'Password123!',
            'device_name' => 'iPhone',
        ])->assertOk();

        $oldAccess = $login->json('data.access_token');
        $oldRefresh = $login->json('data.refresh_token');

        $refresh = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $oldRefresh,
        ])->assertOk();

        $newAccess = $refresh->json('data.access_token');
        $newRefresh = $refresh->json('data.refresh_token');

        $this->assertNotSame($oldAccess, $newAccess);
        $this->assertNotSame($oldRefresh, $newRefresh);

        $this->withToken($oldAccess)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->withToken($newAccess)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $oldRefresh,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('refresh_token');
    }

    public function test_expired_access_token_returns_token_expired_code(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $plain = $user->createToken('iPhone', ['*'], now()->subMinute())->plainTextToken;

        $this->withToken($plain)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'token_expired')
            ->assertJsonPath('message', 'Session expired.');
    }

    public function test_logout_all_revokes_every_session(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'Password123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $first = $this->postJson('/api/v1/auth/login', [
            'login' => 'customer@example.com',
            'password' => 'Password123!',
            'device_name' => 'iPhone',
        ])->assertOk();

        $second = $this->postJson('/api/v1/auth/login', [
            'login' => 'customer@example.com',
            'password' => 'Password123!',
            'device_name' => 'Android',
        ])->assertOk();

        $firstToken = $first->json('data.access_token');
        $secondToken = $second->json('data.access_token');

        $this->assertSame(2, $user->tokens()->count());

        $this->withHeader('Authorization', 'Bearer '.$firstToken)
            ->postJson('/api/v1/auth/logout-all')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out from all devices successfully.');

        $this->assertSame(0, $user->fresh()->tokens()->count());

        // Clear auth guard cache between HTTP calls in the same test process.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$firstToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$secondToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_sessions_lists_active_devices(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'Password123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'login' => 'customer@example.com',
            'password' => 'Password123!',
            'device_name' => 'iPhone',
            'remember_me' => true,
        ])->assertOk();

        $this->withToken($login->json('data.access_token'))
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data.sessions.0.device_name', 'iPhone')
            ->assertJsonPath('data.sessions.0.is_current', true)
            ->assertJsonPath('data.sessions.0.remember_me', true);
    }

    public function test_missing_bearer_still_returns_unauthenticated(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }
}
