<?php

namespace Tests\Feature;

use App\Jobs\SendBookingReminderNotificationsJob;
use App\Models\Order;
use App\Models\User;
use App\Models\UserNotification;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Notifications\Support\NotificationDefinitionRegistry;
use App\Modules\Orders\Events\BookingReminderDue;
use App\Modules\Orders\Events\FlightStatusUpdated;
use App\Modules\Orders\Events\OrderConfirmed;
use App\Modules\Orders\Events\PaymentFailed;
use App\Modules\Orders\Events\PaymentSucceeded;
use App\Models\FinancialTransaction;
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
        $this->assertSame('FLIGHT_STATUS_UPDATED', $updated[0]['code']);
        $this->assertSame('flight_updates', $updated[0]['topic']);
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

        (new SendBookingReminderNotificationsJob)->handle(app(\App\Modules\Notifications\Services\NotificationPreferenceResolver::class));

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

        (new SendBookingReminderNotificationsJob)->handle(app(\App\Modules\Notifications\Services\NotificationPreferenceResolver::class));

        Event::assertNotDispatched(BookingReminderDue::class);
    }

    public function test_new_preferences_default_sms_off(): void
    {
        $customer = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_CUSTOMER]);
        $payload = app(\App\Modules\Notifications\Services\NotificationPreferenceResolver::class)
            ->toPassengerPayload(
                app(\App\Modules\Notifications\Services\NotificationPreferenceResolver::class)->preferencesFor($customer)
            );

        $this->assertFalse($payload['sms']);
        $this->assertTrue($payload['push']);
        $this->assertTrue($payload['email']);
    }
}
