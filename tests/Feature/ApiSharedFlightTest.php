<?php

namespace Tests\Feature;

use App\Models\SharedFlight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSharedFlightTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_and_resolve_a_shared_flight_link(): void
    {
        $create = $this->postJson('/api/v1/shared-flight', [
            'search' => [
                'origin' => 'TIP',
                'destination' => 'IST',
                'departure_date' => '2026-12-01',
            ],
            'selected_offer' => [
                'id' => 'offer-1',
                'price' => ['total' => '420.00', 'currency' => 'LYD'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $token = $create->json('data.share_token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);
        $this->assertStringContainsString('/'.$token, (string) $create->json('data.share_url'));

        $this->getJson('/api/v1/shared-flight/'.$token)
            ->assertOk()
            ->assertJsonPath('data.search.origin', 'TIP')
            ->assertJsonPath('data.search.destination', 'IST')
            ->assertJsonPath('data.selected_offer.id', 'offer-1')
            ->assertJsonPath('data.token', $token)
            ->assertJsonPath('data.is_expired', false);
    }

    public function test_authenticated_create_stores_the_user(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/shared-flight', [
            'search' => ['origin' => 'TIP', 'destination' => 'TUN'],
            'selected_offer' => ['id' => 'offer-2'],
        ])->assertCreated();

        $this->assertDatabaseHas('shared_flights', [
            'user_id' => $customer->id,
        ]);
    }

    public function test_missing_share_token_returns_not_found(): void
    {
        $this->getJson('/api/v1/shared-flight/missing-token')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_expired_share_is_still_readable(): void
    {
        $shared = SharedFlight::query()->create([
            'token' => 'expiredtoken',
            'search' => ['origin' => 'TIP', 'destination' => 'CAI'],
            'selected_offer' => ['id' => 'old'],
            'expires_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/shared-flight/'.$shared->token)
            ->assertOk()
            ->assertJsonPath('data.is_expired', true)
            ->assertJsonPath('data.search.origin', 'TIP');
    }

    public function test_create_requires_search_and_offer(): void
    {
        $this->postJson('/api/v1/shared-flight', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }
}
