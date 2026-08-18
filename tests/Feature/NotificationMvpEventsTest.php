<?php

namespace Tests\Feature;

use App\Jobs\SendBookingReminderNotificationsJob;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\SavedPassenger;
use App\Models\User;
use App\Models\UserNotification;
use App\Modules\Notifications\Events\PassengerActionDue;
use App\Modules\Notifications\Services\JourneyCampaignDispatcher;
use App\Modules\Notifications\Services\NotificationPreferenceResolver;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Notifications\Support\NotificationDefinitionRegistry;
use App\Modules\Notifications\Support\NotificationInboxContract;
use App\Modules\Notifications\Support\NotificationTopics;
use App\Modules\Orders\Events\BookingReminderDue;
use App\Modules\Orders\Events\FlightStatusUpdated;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderConfirmed;
use App\Modules\Orders\Events\PaymentFailed;
use App\Modules\Orders\Events\PaymentSucceeded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationMvpEventsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(User $customer, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'customer_id' => $customer->id,
            'provider_name' => 'Test Provider',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 100,
            'booking_reference' => 'CP-TEST-'.uniqid(),
            'details' => [],
            'request_payload' => [],
        ], $overrides));
    }

    public function test_payment_succeeded_definition_includes_push_and_deep_link(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_PAID,
            'booking_reference' => 'CP-TEST-1',
        ]);
        $tx = FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => 100,
            'currency' => 'LYD',
            'source' => FinancialTransaction::SOURCE_PAYMENT_STATUS_PAID,
        ]);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new PaymentSucceeded($order->load('customer'), $tx));

        $this->assertSame('PAYMENT_SUCCEEDED', $defs[0]['code']);
        $this->assertContains(NotificationChannels::PUSH, $defs[0]['channels']);
        $this->assertNotContains(NotificationChannels::SMS, $defs[0]['channels']);
        $this->assertSame('/my-orders', $defs[0]['payload']['deep_link']);
        $this->assertSame('payment', $defs[0]['notification_type']);
    }

    public function test_hotel_confirmed_uses_hotel_topic_and_success_type(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'total_amount' => 200,
            'booking_reference' => 'CP-HOTEL-1',
        ]);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new OrderConfirmed($order->load('customer')));

        $this->assertSame('HOTEL_BOOKING_CONFIRMED', $defs[0]['code']);
        $this->assertSame('hotel', $defs[0]['topic']);
        $this->assertSame('success', $defs[0]['notification_type']);
    }

    public function test_flight_ticketed_uses_success_template(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'total_amount' => 150,
            'booking_reference' => 'CP-FLT-1',
        ]);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new OrderConfirmed($order->load('customer')));

        $this->assertSame('FLIGHT_TICKET_ISSUED', $defs[0]['code']);
        $this->assertSame('success', $defs[0]['notification_type']);
        $this->assertSame('OFFER_INSURANCE', $defs[0]['payload']['next_best_offer']['code']);
    }

    public function test_payment_failed_and_flight_update_definitions_exist(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_FAILED,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'total_amount' => 90,
            'booking_reference' => 'CP-FAIL-1',
        ]);

        $failed = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new PaymentFailed($order->load('customer'), 'Card declined'));
        $this->assertSame('PAYMENT_FAILED', $failed[0]['code']);

        $order->forceFill(['status' => Order::STATUS_CONFIRMED])->save();
        $updated = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new FlightStatusUpdated($order->load('customer'), ['gate' => ['from' => 'A1', 'to' => 'B2']], 'Gate changed'));
        $this->assertSame('FLIGHT_GATE_CHANGED', $updated[0]['code']);
        $this->assertNull($updated[0]['topic']);
        $this->assertSame('A1', $updated[0]['payload']['from_value']);
        $this->assertSame('B2', $updated[0]['payload']['to_value']);

        $assigned = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new FlightStatusUpdated($order, ['gate' => ['from' => null, 'to' => 'A12']], 'Gate assigned'));
        $this->assertSame('GATE_ASSIGNED', $assigned[0]['code']);
        $this->assertSame('A12', $assigned[0]['payload']['to_value']);
    }

    public function test_reminder_job_dispatches_once_for_24h_window(): void
    {
        Event::fake([BookingReminderDue::class]);

        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'total_amount' => 120,
            'booking_reference' => 'CP-REM-1',
            'details' => [
                'departure_time' => now()->addHours(24)->toIso8601String(),
            ],
        ]);

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertDispatched(BookingReminderDue::class, function (BookingReminderDue $event): bool {
            return $event->window === BookingReminderDue::WINDOW_24H;
        });
    }

    public function test_reminder_job_skips_when_already_sent(): void
    {
        Event::fake([BookingReminderDue::class]);

        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'total_amount' => 120,
            'booking_reference' => 'CP-REM-2',
            'details' => [
                'departure_time' => now()->addHours(24)->toIso8601String(),
            ],
        ]);

        UserNotification::query()->create([
            'user_id' => $customer->id,
            'template_code' => 'FLIGHT_REMINDER_24H',
            'type' => 'flight',
            'title' => 'Reminder',
            'message' => 'Already sent',
            'related_type' => 'order',
            'related_id' => $order->id,
            'delivered_at' => now(),
        ]);

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertNotDispatched(BookingReminderDue::class, function (BookingReminderDue $event): bool {
            return $event->window === BookingReminderDue::WINDOW_24H;
        });
    }

    public function test_journey_job_sends_1h_reminder(): void
    {
        Event::fake([BookingReminderDue::class]);

        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'booking_reference' => 'CP-REM-1H',
            'details' => [
                'departure_time' => now()->addHour()->toIso8601String(),
                'destination' => 'Tunis',
            ],
        ]);

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertDispatched(BookingReminderDue::class, fn (BookingReminderDue $event): bool => $event->window === BookingReminderDue::WINDOW_1H);
    }

    public function test_3h_reminder_embeds_esim_and_insurance_offers_on_same_card(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'booking_reference' => 'CP-3H-1',
            'details' => [
                'origin' => 'Tripoli',
                'destination' => 'Tunis',
                'departure_airport' => 'MJI',
                'arrival_airport' => 'TUN',
                'departure_time' => now()->addHours(3)->toIso8601String(),
            ],
        ]);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new BookingReminderDue($order->load('customer'), BookingReminderDue::WINDOW_3H));

        $this->assertSame('FLIGHT_REMINDER_3H', $defs[0]['code']);
        $this->assertSame('/my-orders/'.$order->id, $defs[0]['payload']['deep_link']);
        $this->assertSame('TN', $defs[0]['payload']['destination_country']);
        $this->assertNotContains(NotificationChannels::EMAIL, $defs[0]['channels']);
        $this->assertContains('OFFER_ESIM', array_column($defs[0]['payload']['offers'], 'code'));
        $this->assertContains('OFFER_INSURANCE', array_column($defs[0]['payload']['offers'], 'code'));
        $this->assertSame('OFFER_ESIM', $defs[0]['payload']['next_best_offer']['code']);
        $this->assertSame('/esim?country=TN', $defs[0]['payload']['offers'][0]['deep_link']);
        $this->assertNotEmpty($defs[0]['payload']['checklist']);
    }

    public function test_3h_card_skips_esim_when_user_has_tunisia_esim(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'booking_reference' => 'CP-3H-2',
            'details' => [
                'destination' => 'Tunis',
                'arrival_airport' => 'TUN',
            ],
        ]);
        $this->makeOrder($customer, [
            'service_type' => Order::SERVICE_TYPE_ESIM,
            'status' => Order::STATUS_CONFIRMED,
            'booking_reference' => 'CP-ESIM-TN',
            'total_amount' => 20,
            'details' => ['destination_country' => 'TN'],
        ]);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new BookingReminderDue($order->load('customer'), BookingReminderDue::WINDOW_3H));

        $this->assertNotContains('OFFER_ESIM', array_column($defs[0]['payload']['offers'], 'code'));
        $this->assertContains('OFFER_INSURANCE', array_column($defs[0]['payload']['offers'], 'code'));
    }

    public function test_1h_reminder_is_not_promotional_and_opens_order(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'booking_reference' => 'CP-1H-1',
            'details' => [
                'origin' => 'Tripoli',
                'destination' => 'Tunis',
                'departure_time' => now()->addHour()->toIso8601String(),
            ],
        ]);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new BookingReminderDue($order->load('customer'), BookingReminderDue::WINDOW_1H));

        $this->assertSame('FLIGHT_REMINDER_1H', $defs[0]['code']);
        $this->assertSame('flight', $defs[0]['notification_type']);
        $this->assertSame(NotificationTopics::BOOKING_REMINDERS, $defs[0]['topic']);
        $this->assertSame('/my-orders/'.$order->id, $defs[0]['payload']['deep_link']);
        $this->assertSame([], $defs[0]['payload']['offers']);
    }

    public function test_arrival_card_includes_hotel_offer_unless_hotel_already_booked(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $arrival = now()->subMinutes(20);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'booking_reference' => 'CP-TUN-1',
            'details' => [
                'destination' => 'Tunis',
                'arrival_airport' => 'TUN',
                'arrival_time' => $arrival->toIso8601String(),
            ],
        ]);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new BookingReminderDue($order->load('customer'), BookingReminderDue::WINDOW_ARRIVAL));

        $this->assertSame('DESTINATION_ARRIVAL', $defs[0]['code']);
        $this->assertContains('OFFER_HOTELS_AT_DESTINATION', array_column($defs[0]['payload']['offers'], 'code'));
        $this->assertSame('OFFER_HOTELS_AT_DESTINATION', $defs[0]['payload']['next_best_offer']['code']);
        $this->assertSame('/hotels?city=tunis', $defs[0]['payload']['offers'][0]['deep_link']);

        $this->makeOrder($customer, [
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'status' => Order::STATUS_CONFIRMED,
            'booking_reference' => 'CP-HTL-TUN',
            'total_amount' => 80,
            'details' => [
                'destination' => 'Tunis',
                'check_in' => $arrival->toDateString(),
            ],
        ]);

        $without = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new BookingReminderDue($order->load('customer'), BookingReminderDue::WINDOW_ARRIVAL));

        $this->assertNotContains('OFFER_HOTELS_AT_DESTINATION', array_column($without[0]['payload']['offers'], 'code'));
        $this->assertSame('OFFER_CARS_AT_DESTINATION', $without[0]['payload']['next_best_offer']['code']);
    }

    public function test_booking_reminder_includes_email_channel(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'booking_reference' => 'CP-REM-EMAIL',
        ]);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new BookingReminderDue($order->load('customer'), BookingReminderDue::WINDOW_24H));

        $this->assertContains(NotificationChannels::EMAIL, $defs[0]['channels']);
    }

    public function test_new_preferences_default_sms_off(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $payload = app(NotificationPreferenceResolver::class)
            ->toPassengerPayload(
                app(NotificationPreferenceResolver::class)->preferencesFor($customer)
            );

        $this->assertFalse($payload['sms']);
        $this->assertTrue($payload['push']);
        $this->assertTrue($payload['email']);
    }

    public function test_24h_reminder_embeds_trip_checklist(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'booking_reference' => 'CP-CHK-1',
            'details' => [
                'origin' => 'Tripoli',
                'destination' => 'Tunis',
                'arrival_airport' => 'TUN',
            ],
        ]);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new BookingReminderDue($order->load('customer'), BookingReminderDue::WINDOW_24H));

        $this->assertSame('before_departure', $defs[0]['payload']['stage']);
        $this->assertStringContainsString('eSIM', $defs[0]['payload']['checklist_hint']);
        $this->assertSame('OFFER_ESIM', $defs[0]['payload']['next_best_offer']['code']);
    }

    public function test_airline_cancellation_is_operational_and_actionable(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_CANCELLED,
            'booking_reference' => 'CP-CXL-1',
            'details' => [
                'origin' => 'Tripoli',
                'destination' => 'Tunis',
                'departure_time' => '2026-09-20T15:30:00+02:00',
            ],
        ]);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new OrderCancelled($order->load('customer'), OrderCancelled::SOURCE_AIRLINE));

        $this->assertSame('FLIGHT_CANCELLED', $defs[0]['code']);
        $this->assertNull($defs[0]['topic']);

        $inbox = NotificationInboxContract::enrich(
            $defs[0]['code'],
            $defs[0]['payload'],
        );

        $this->assertSame('operational', $inbox['family']);
        $this->assertSame('critical', $inbox['severity']);
        $this->assertContains('view_alternatives', array_column($inbox['actions'], 'code'));
        $this->assertContains('request_refund', array_column($inbox['actions'], 'code'));
        $this->assertSame('passenger', $inbox['recipient']);
        $this->assertTrue($inbox['action_engine']);
        $this->assertNotEmpty($inbox['expires_at']);
        $this->assertSame(6, NotificationInboxContract::expiryHours('FLIGHT_CANCELLED'));
    }

    public function test_customer_cancellation_is_not_airline_template(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_CANCELLED,
            'booking_reference' => 'CP-CXL-2',
        ]);

        $defs = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new OrderCancelled($order->load('customer'), OrderCancelled::SOURCE_CUSTOMER));

        $this->assertSame('BOOKING_CANCELLED', $defs[0]['code']);
    }

    public function test_checkin_open_window_and_wallet_action_definitions(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'booking_reference' => 'CP-CIN-1',
            'details' => [
                'origin' => 'Tripoli',
                'destination' => 'Tunis',
                'departure_time' => now()->addHours(48)->toIso8601String(),
            ],
        ]);

        $checkin = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new BookingReminderDue($order->load('customer'), BookingReminderDue::WINDOW_CHECKIN_OPEN));

        $this->assertSame('ONLINE_CHECKIN_OPEN', $checkin[0]['code']);

        $inbox = NotificationInboxContract::enrich(
            $checkin[0]['code'],
            $checkin[0]['payload'],
        );
        $this->assertSame('journey', $inbox['family']);
        $this->assertContains('check_in', array_column($inbox['actions'], 'code'));

        $wallet = app(NotificationDefinitionRegistry::class)
            ->definitionsFor(new PassengerActionDue(
                $customer,
                'WALLET_TOPUP_SUCCESS',
                ['amount' => '500.00', 'currency' => 'LYD', 'deep_link' => '/wallet'],
                'wallet_transaction',
                11,
            ));

        $this->assertSame('WALLET_TOPUP_SUCCESS', $wallet[0]['code']);
        $this->assertNull($wallet[0]['topic']);
        $this->assertSame(11, $wallet[0]['related_id']);
        $this->assertSame('wallet', NotificationInboxContract::category('WALLET_TOPUP_SUCCESS'));
    }

    public function test_checkin_open_job_dispatches_around_48h(): void
    {
        Event::fake([BookingReminderDue::class]);

        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'total_amount' => 120,
            'booking_reference' => 'CP-CIN-2',
            'details' => [
                'departure_time' => now()->addHours(48)->toIso8601String(),
            ],
        ]);

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertDispatched(BookingReminderDue::class, function (BookingReminderDue $event): bool {
            return $event->window === BookingReminderDue::WINDOW_CHECKIN_OPEN;
        });
    }

    public function test_passport_expiry_reminder_dispatches_once(): void
    {
        Event::fake([PassengerActionDue::class]);

        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        SavedPassenger::factory()->create([
            'user_id' => $customer->id,
            'passport_expiry' => now()->addDays(20)->toDateString(),
        ]);
        $this->makeOrder($customer, [
            'status' => Order::STATUS_TICKETED,
            'details' => [
                'origin' => 'Tripoli',
                'destination' => 'Tunis',
                'departure_time' => now()->addDays(10)->toIso8601String(),
            ],
        ]);

        (new SendBookingReminderNotificationsJob)->handle(app(JourneyCampaignDispatcher::class));

        Event::assertDispatched(PassengerActionDue::class, function (PassengerActionDue $event) use ($customer): bool {
            return $event->code === 'PASSPORT_EXPIRY_REMINDER'
                && $event->user->is($customer)
                && $event->payload['destination'] === 'Tunis';
        });
    }
}
