<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Api\Auth\Contracts\GoogleIdTokenVerifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GoogleAuthApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array{sub: string, email: string, name?: string, picture?: string, email_verified?: bool|string}  $claims
     */
    private function mockGoogleToken(array $claims): void
    {
        $verifier = $this->createMock(GoogleIdTokenVerifierInterface::class);
        $verifier->method('verify')->willReturn($claims);

        $this->app->instance(GoogleIdTokenVerifierInterface::class, $verifier);
    }

    public function test_email_login_does_not_construct_google_verifier(): void
    {
        $this->app->singleton(GoogleIdTokenVerifierInterface::class, function (): never {
            throw new HttpException(
                500,
                'Google sign-in requires phpseclib/phpseclib. Run: composer require phpseclib/phpseclib:^3.0',
            );
        });

        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'Password123!',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'customer@example.com',
            'password' => 'Password123!',
            'device_name' => 'mobile_app',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'customer@example.com');
    }

    public function test_google_auth_creates_customer_and_returns_token_pair(): void
    {
        $this->mockGoogleToken([
            'sub' => 'google-sub-123',
            'email' => 'ahmad@gmail.com',
            'name' => 'Ahmad',
            'picture' => 'https://example.com/avatar.jpg',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'provider' => 'google',
            'id_token' => 'valid-token',
            'device_name' => 'mobile_app',
            'remember_me' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.remember_me', true)
            ->assertJsonPath('data.user.email', 'ahmad@gmail.com')
            ->assertJsonPath('data.user.name', 'Ahmad')
            ->assertJsonPath('data.user.phone', null)
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token',
                    'expires_in',
                    'expires_at',
                    'remember_me',
                    'token',
                    'user' => ['id', 'email', 'phone'],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'ahmad@gmail.com',
            'google_id' => 'google-sub-123',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'phone' => null,
        ]);

        $this->withToken($response->json('data.access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'ahmad@gmail.com');
    }

    public function test_google_auth_logs_in_existing_customer_and_links_google_id(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@gmail.com',
            'password' => 'Password123!',
            'phone' => '+15550000001',
            'google_id' => null,
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->mockGoogleToken([
            'sub' => 'google-sub-456',
            'email' => 'existing@gmail.com',
            'name' => 'Existing User',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'provider' => 'google',
            'id_token' => 'valid-token',
            'device_name' => 'mobile_app',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.phone', '+15550000001');

        $this->assertSame('google-sub-456', $user->fresh()->google_id);
    }

    public function test_google_auth_returns_401_for_invalid_token(): void
    {
        $verifier = $this->createMock(GoogleIdTokenVerifierInterface::class);
        $verifier->method('verify')->willThrowException(
            new HttpException(401, 'The Google ID token is invalid or has expired.'),
        );
        $this->app->instance(GoogleIdTokenVerifierInterface::class, $verifier);

        $this->postJson('/api/v1/auth/google', [
            'provider' => 'google',
            'id_token' => 'bad-token',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The Google ID token is invalid or has expired.');
    }

    public function test_google_auth_logs_access_token_verify_failure_reason(): void
    {
        $logged = [];
        Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$logged): void {
            $logged[] = [
                'level' => $event->level,
                'message' => $event->message,
                'context' => $event->context,
            ];
        });

        $this->postJson('/api/v1/auth/google', [
            'provider' => 'google',
            'id_token' => 'not-a-valid-jwt',
        ])->assertUnauthorized();

        $failure = collect($logged)->first(
            fn (array $entry): bool => ($entry['message'] ?? '') === 'Google ID token verification failed.',
        );

        $this->assertNotNull($failure);
        $this->assertNotEmpty($failure['context']['message'] ?? null);
        $this->assertNotEmpty($failure['context']['exception'] ?? null);
    }

    public function test_google_auth_returns_409_when_email_linked_to_different_google_account(): void
    {
        User::factory()->create([
            'email' => 'linked@gmail.com',
            'google_id' => 'other-google-sub',
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->mockGoogleToken([
            'sub' => 'new-google-sub',
            'email' => 'linked@gmail.com',
            'name' => 'Linked User',
            'email_verified' => true,
        ]);

        $this->postJson('/api/v1/auth/google', [
            'provider' => 'google',
            'id_token' => 'valid-token',
        ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This email is linked to a different Google account.');
    }

    public function test_google_auth_returns_422_for_missing_fields(): void
    {
        $this->postJson('/api/v1/auth/google', [
            'provider' => 'google',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('id_token');
    }

    public function test_google_auth_rejects_admin_accounts(): void
    {
        User::factory()->create([
            'email' => 'admin@gmail.com',
            'google_id' => null,
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->mockGoogleToken([
            'sub' => 'google-sub-admin',
            'email' => 'admin@gmail.com',
            'name' => 'Admin User',
            'email_verified' => true,
        ]);

        $this->postJson('/api/v1/auth/google', [
            'provider' => 'google',
            'id_token' => 'valid-token',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('id_token');
    }
}
