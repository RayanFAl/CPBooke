<?php

namespace Tests\Feature;

use App\Jobs\SendBookingReminderNotificationsJob;
use App\Models\Order;
use App\Models\PriceAlert;
use App\Models\TravelSearchIntent;
use App\Models\User;
use App\Modules\Notifications\Events\AbandonedFlightSearchDue;
use App\Modules\Notifications\Events\PriceAlertHit;
use App\Modules\Notifications\Services\JourneyCampaignDispatcher;
use App\Modules\Notifications\Support\NotificationDefinitionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TravelMarketingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_intent_api_upserts_route_and_marks_converted_when_booked(): void
    {
        $user = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/search-intents', [
            'origin' => 'TIP',
            'destination' => 'IST',
            'departure_date' => '2026-09-20',
            'lowest_price' => 1250,
            'currency' => 'LYD',
        ])
            ->assertOk()
            ->assertJsonPath('data.origin', 'TIP')
            ->assertJsonPath('data.last_seen_price', 1250)
            ->assertJsonPath('data.converted', false);

        $this->assertDatabaseCount('travel_search_intents', 1);

        Order::query()->create([
            'customer_id' => $user->id,
            'provider_name' => 'Test Provider',
            'status' => Order::STATUS_TICKETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 1250,
            'booking_reference' => 'CP-IST-1',
            'details' => [
                'origin' => 'TIP',
                'destination' => 'IST',
                'departure_time' => '2026-09-20T08:00:00+02:00',
            ],
            'request_payload' => [],
        ]);

        $this->postJson('/api/v1/notifications/search-intents', [
            'origin' => 'TIP',
            'destination' => 'IST',
            'departure_date' => '2026-09-20',
            'lowest_price' => 1180,
        ])
            ->assertOk()
            ->assertJsonPath('data.converted', true);

        $this->assertDatabaseCount('travel_search_intents', 1);
    }

    public function test_abandoned_search_dispatches_once_for_unbooked_intent(): void
    {
        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class]);

        $user = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $intent = TravelSearchIntent::query()->create([
            'user_id' => $user->id,
            'origin' => 'TIP',
            'destination' => 'IST',
            'route_key' => TravelSearchIntent::routeKeyFor('TIP', 'IST', '2026-09-20'),
            'departure_date' => '2026-09-20',
            'last_seen_price' => 1250,
            'currency' => 'LYD',
            'last_searched_at' => now()->subHours(3),
        ]);

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertDispatched(AbandonedFlightSearchDue::class, function (AbandonedFlightSearchDue $event) use ($intent): bool {
            return $event->intent->is($intent);
        });

        $this->assertNotNull($intent->fresh()->abandoned_notified_at);

        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class]);
        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));
        Event::assertNotDispatched(AbandonedFlightSearchDue::class);
    }

    public function test_price_alert_fires_when_latest_search_is_at_or_below_target(): void
    {
        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class]);

        $user = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/price-alerts', [
            'origin' => 'TIP',
            'destination' => 'TUN',
            'departure_date' => '2026-10-01',
            'target_price' => 800,
            'currency' => 'LYD',
        ])->assertOk();

        TravelSearchIntent::query()->create([
            'user_id' => $user->id,
            'origin' => 'TIP',
            'destination' => 'TUN',
            'route_key' => TravelSearchIntent::routeKeyFor('TIP', 'TUN', '2026-10-01'),
            'departure_date' => '2026-10-01',
            'last_seen_price' => 780,
            'currency' => 'LYD',
            'last_searched_at' => now()->subMinutes(10),
        ]);

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertDispatched(PriceAlertHit::class, function (PriceAlertHit $event): bool {
            return $event->currentPrice === 780.0;
        });

        $alert = PriceAlert::query()->firstOrFail();
        $this->assertSame('780.00', (string) $alert->last_triggered_price);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new PriceAlertHit($alert->load('user'), 780));

        $this->assertSame('PRICE_ALERT_HIT', $defs[0]['code']);
        $this->assertStringContainsString('/flights?', $defs[0]['payload']['deep_link']);
    }

    public function test_price_alert_can_be_disabled_by_owner(): void
    {
        $user = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        Sanctum::actingAs($user);

        $alert = PriceAlert::query()->create([
            'user_id' => $user->id,
            'origin' => 'TIP',
            'destination' => 'TUN',
            'route_key' => TravelSearchIntent::routeKeyFor('TIP', 'TUN', '2026-10-01'),
            'departure_date' => '2026-10-01',
            'target_price' => 800,
            'currency' => 'LYD',
            'is_active' => true,
        ]);

        $this->deleteJson('/api/v1/notifications/price-alerts/'.$alert->id)
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }
}
