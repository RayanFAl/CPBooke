<?php

namespace Tests\Feature;

use App\Jobs\SendBookingReminderNotificationsJob;
use App\Models\Order;
use App\Models\PriceAlert;
use App\Models\SeatAlert;
use App\Models\TravelSearchIntent;
use App\Models\User;
use App\Modules\Notifications\Events\AbandonedFlightSearchDue;
use App\Modules\Notifications\Events\PriceAlertHit;
use App\Modules\Notifications\Events\SeatAlertAvailable;
use App\Modules\Notifications\Events\SeatAlertStillWatching;
use App\Modules\Notifications\Services\JourneyCampaignDispatcher;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Notifications\Support\NotificationDefinitionRegistry;
use App\Modules\Notifications\Support\NotificationInboxContract;
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
            'available_seats' => 4,
            'currency' => 'LYD',
        ])
            ->assertOk()
            ->assertJsonPath('data.origin', 'TIP')
            ->assertJsonPath('data.last_seen_price', 1250)
            ->assertJsonPath('data.last_seen_seats', 4)
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
        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class, SeatAlertAvailable::class, SeatAlertStillWatching::class]);

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

        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class, SeatAlertAvailable::class, SeatAlertStillWatching::class]);
        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));
        Event::assertNotDispatched(AbandonedFlightSearchDue::class);
    }

    public function test_price_alert_fires_when_latest_search_is_at_or_below_target(): void
    {
        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class, SeatAlertAvailable::class, SeatAlertStillWatching::class]);

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

    public function test_seat_alert_fires_when_latest_search_meets_min_seats(): void
    {
        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class, SeatAlertAvailable::class, SeatAlertStillWatching::class]);

        $user = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/seat-alerts', [
            'origin' => 'TIP',
            'destination' => 'TUN',
            'departure_date' => '2026-10-01',
            'flight_number' => '8U401',
            'cabin' => 'economy',
            'min_seats' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.min_seats', 2)
            ->assertJsonPath('data.flight_number', '8U401');

        TravelSearchIntent::query()->create([
            'user_id' => $user->id,
            'origin' => 'TIP',
            'destination' => 'TUN',
            'route_key' => TravelSearchIntent::routeKeyFor('TIP', 'TUN', '2026-10-01'),
            'departure_date' => '2026-10-01',
            'flight_number' => '8U401',
            'cabin' => 'economy',
            'last_seen_seats' => 3,
            'currency' => 'LYD',
            'last_searched_at' => now()->subMinutes(10),
        ]);

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertDispatched(SeatAlertAvailable::class, function (SeatAlertAvailable $event): bool {
            return $event->availableSeats === 3;
        });

        $alert = SeatAlert::query()->firstOrFail();
        $this->assertSame(3, (int) $alert->last_triggered_seats);
        $this->assertFalse($alert->is_active);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new SeatAlertAvailable($alert->load('user'), 3));

        $this->assertSame('SEAT_ALERT_AVAILABLE', $defs[0]['code']);
        $this->assertContains(NotificationChannels::PUSH, $defs[0]['channels']);
        $this->assertContains(NotificationChannels::IN_APP, $defs[0]['channels']);
        $this->assertStringContainsString('/flights?', $defs[0]['payload']['deep_link']);
        $this->assertStringContainsString('origin=TIP', $defs[0]['payload']['deep_link']);
    }

    public function test_seat_alert_sends_periodic_still_watching_reminder(): void
    {
        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class, SeatAlertAvailable::class, SeatAlertStillWatching::class]);

        $user = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $routeKey = TravelSearchIntent::routeKeyFor('TIP', 'TUN', '2026-10-01');
        $alert = $this->ageSeatAlert(SeatAlert::query()->create([
            'user_id' => $user->id,
            'origin' => 'TIP',
            'destination' => 'TUN',
            'route_key' => $routeKey,
            'watch_key' => SeatAlert::watchKeyFor($routeKey, '8U401', null, 'economy'),
            'departure_date' => '2026-10-01',
            'flight_number' => '8U401',
            'cabin' => 'economy',
            'min_seats' => 2,
            'is_active' => true,
        ]), 13);

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertDispatched(SeatAlertStillWatching::class, function (SeatAlertStillWatching $event) use ($alert): bool {
            return $event->alert->is($alert);
        });
        Event::assertNotDispatched(SeatAlertAvailable::class);

        $alert->refresh();
        $this->assertTrue($alert->is_active);
        $this->assertNotNull($alert->last_reminded_at);

        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class, SeatAlertAvailable::class, SeatAlertStillWatching::class]);
        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));
        Event::assertNotDispatched(SeatAlertStillWatching::class);

        $this->travel(12)->hours();

        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class, SeatAlertAvailable::class, SeatAlertStillWatching::class]);
        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));
        Event::assertDispatched(SeatAlertStillWatching::class);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new SeatAlertStillWatching($alert->load('user')));

        $this->assertSame('SEAT_ALERT_STILL_WATCHING', $defs[0]['code']);
        $this->assertContains(NotificationChannels::PUSH, $defs[0]['channels']);
        $this->assertStringContainsString('still looking for a seat', strtolower($defs[0]['body']));
        $this->assertSame('marketing', NotificationInboxContract::family('SEAT_ALERT_STILL_WATCHING'));
    }

    public function test_fresh_seat_alert_does_not_remind_immediately(): void
    {
        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class, SeatAlertAvailable::class, SeatAlertStillWatching::class]);

        $user = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/seat-alerts', [
            'origin' => 'TIP',
            'destination' => 'TUN',
            'departure_date' => '2026-10-01',
            'min_seats' => 1,
        ])->assertOk();

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertNotDispatched(SeatAlertStillWatching::class);
        $this->assertNull(SeatAlert::query()->firstOrFail()->last_reminded_at);
    }

    public function test_seat_alert_stops_reminders_after_seats_become_available(): void
    {
        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class, SeatAlertAvailable::class, SeatAlertStillWatching::class]);

        $user = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $routeKey = TravelSearchIntent::routeKeyFor('TIP', 'TUN', '2026-10-01');
        $this->ageSeatAlert(SeatAlert::query()->create([
            'user_id' => $user->id,
            'origin' => 'TIP',
            'destination' => 'TUN',
            'route_key' => $routeKey,
            'watch_key' => SeatAlert::watchKeyFor($routeKey, null, null, null),
            'departure_date' => '2026-10-01',
            'min_seats' => 2,
            'is_active' => true,
        ]), 13);

        TravelSearchIntent::query()->create([
            'user_id' => $user->id,
            'origin' => 'TIP',
            'destination' => 'TUN',
            'route_key' => $routeKey,
            'departure_date' => '2026-10-01',
            'last_seen_seats' => 4,
            'currency' => 'LYD',
            'last_searched_at' => now()->subMinutes(10),
        ]);

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertDispatched(SeatAlertAvailable::class);
        Event::assertNotDispatched(SeatAlertStillWatching::class);
        $this->assertFalse(SeatAlert::query()->firstOrFail()->is_active);

        $this->travel(12)->hours();
        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class, SeatAlertAvailable::class, SeatAlertStillWatching::class]);
        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));
        Event::assertNotDispatched(SeatAlertStillWatching::class);
        Event::assertNotDispatched(SeatAlertAvailable::class);
    }

    public function test_expired_seat_alert_is_deactivated_without_reminder(): void
    {
        Event::fake([AbandonedFlightSearchDue::class, PriceAlertHit::class, SeatAlertAvailable::class, SeatAlertStillWatching::class]);

        $user = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $routeKey = TravelSearchIntent::routeKeyFor('TIP', 'TUN', now()->subDay()->toDateString());
        SeatAlert::query()->create([
            'user_id' => $user->id,
            'origin' => 'TIP',
            'destination' => 'TUN',
            'route_key' => $routeKey,
            'watch_key' => SeatAlert::watchKeyFor($routeKey, null, null, null),
            'departure_date' => now()->subDay()->toDateString(),
            'min_seats' => 1,
            'is_active' => true,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertNotDispatched(SeatAlertStillWatching::class);
        $this->assertFalse(SeatAlert::query()->firstOrFail()->is_active);
    }

    public function test_seat_alert_can_be_disabled_by_owner(): void
    {
        $user = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        Sanctum::actingAs($user);

        $routeKey = TravelSearchIntent::routeKeyFor('TIP', 'TUN', '2026-10-01');
        $alert = SeatAlert::query()->create([
            'user_id' => $user->id,
            'origin' => 'TIP',
            'destination' => 'TUN',
            'route_key' => $routeKey,
            'watch_key' => SeatAlert::watchKeyFor($routeKey, null, null, null),
            'departure_date' => '2026-10-01',
            'min_seats' => 2,
            'is_active' => true,
        ]);

        $this->deleteJson('/api/v1/notifications/seat-alerts/'.$alert->id)
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    private function ageSeatAlert(SeatAlert $alert, int $hours): SeatAlert
    {
        $agedAt = now()->subHours($hours);
        $alert->timestamps = false;
        $alert->forceFill([
            'created_at' => $agedAt,
            'updated_at' => $agedAt,
        ])->save();

        return $alert->refresh();
    }
}
