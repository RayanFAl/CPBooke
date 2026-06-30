<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserNotificationPreference;
use App\Modules\Api\DTO\CreateOrderDTO;
use App\Modules\Admin\Finance\Events\CriticalFinanceAnomaliesDetected;
use App\Modules\Admin\Finance\Services\FinancialConsistencyService;
use App\Modules\Api\Orders\Services\OrderService;
use App\Modules\Notifications\Jobs\SendNotificationChannelJob;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Loyalty\Events\LoyaltyTierChanged;
use App\Modules\Orders\Events\OrderConfirmed;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\RefundIssued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_service_dispatches_notification_events_for_order_creation_and_refund(): void
    {
        Event::fake([OrderCreated::class, RefundIssued::class]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $service = app(OrderService::class);

        $order = $service->createForCustomer($customer, new CreateOrderDTO(
            providerName: 'Notification Provider',
            currency: 'USD',
            totalAmount: '120.00',
            serviceType: Order::SERVICE_TYPE_FLIGHT,
            details: ['pnr' => 'NTF001'],
            requestPayload: ['pnr' => 'NTF001'],
        ));

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '120.00',
            'currency' => 'USD',
            'source' => FinancialTransaction::SOURCE_PAYMENT_STATUS_PAID,
        ]);

        $service->updatePaymentStatusByActor($order->refresh(), Order::PAYMENT_STATUS_REFUNDED, $actor, [
            'reason' => 'Customer requested a full refund.',
        ]);

        Event::assertDispatched(OrderCreated::class);
        Event::assertDispatched(RefundIssued::class, fn (RefundIssued $event): bool => $event->order->is($order));
    }

    public function test_notification_service_creates_logs_and_queues_channel_jobs_for_order_confirmation(): void
    {
        Queue::fake();

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'phone' => '+966500000001',
        ]);

        $customer->notificationDevices()->create([
            'channel' => NotificationChannels::PUSH,
            'platform' => 'ios',
            'device_token' => 'device-token-001',
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Dispatch Provider',
            'booking_reference' => 'BK-NOTIFY-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Dispatch Suites'],
            'currency' => 'USD',
            'total_amount' => 250.00,
            'request_payload' => ['hotel_name' => 'Dispatch Suites'],
        ]);

        app(NotificationService::class)->dispatchForEvent(new OrderConfirmed($order->load('customer')));

        $this->assertDatabaseCount('notification_logs', 3);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'channel' => NotificationChannels::IN_APP,
            'template_code' => 'ORDER_CONFIRMED',
            'status' => NotificationLog::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'channel' => NotificationChannels::EMAIL,
            'template_code' => 'ORDER_CONFIRMED',
            'status' => NotificationLog::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'channel' => NotificationChannels::PUSH,
            'template_code' => 'ORDER_CONFIRMED',
            'status' => NotificationLog::STATUS_PENDING,
        ]);

        Queue::assertPushed(SendNotificationChannelJob::class, 3);

        $template = NotificationTemplate::query()->where('code', 'ORDER_CONFIRMED')->firstOrFail();

        $this->assertSame(
            [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP],
            $template->channels,
        );
    }

    public function test_notification_api_lists_unread_notifications_and_marks_them_as_read(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $otherCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $log = NotificationLog::query()->create([
            'user_id' => $customer->id,
            'channel' => NotificationChannels::IN_APP,
            'template_code' => 'ORDER_CREATED',
            'event_class' => OrderCreated::class,
            'notification_type' => 'order',
            'subject' => 'Order created',
            'body' => 'Your order was created.',
            'status' => NotificationLog::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $unread = UserNotification::query()->create([
            'user_id' => $customer->id,
            'notification_log_id' => $log->id,
            'template_code' => 'ORDER_CREATED',
            'type' => 'order',
            'title' => 'Order created',
            'message' => 'Your order was created.',
            'delivered_at' => now(),
        ]);

        UserNotification::query()->create([
            'user_id' => $customer->id,
            'notification_log_id' => $log->id,
            'template_code' => 'ORDER_CREATED',
            'type' => 'order',
            'title' => 'Order created again',
            'message' => 'This one is already read.',
            'read_at' => now(),
            'delivered_at' => now(),
        ]);

        UserNotification::query()->create([
            'user_id' => $otherCustomer->id,
            'template_code' => 'ORDER_CREATED',
            'type' => 'order',
            'title' => 'Foreign notification',
            'message' => 'Should not appear.',
            'delivered_at' => now(),
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.notifications');

        $this->getJson('/api/v1/notifications/unread')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.notifications.0.id', $unread->id)
            ->assertJsonPath('data.notifications.0.is_read', false);

        $this->postJson('/api/v1/notifications/mark-as-read', [
            'notification_ids' => [$unread->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.marked_count', 1);

        $this->assertNotNull($unread->fresh()->read_at);
    }

    public function test_admin_can_retry_failed_logs_and_update_templates(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        $template = NotificationTemplate::query()->create([
            'code' => 'REFUND_ISSUED',
            'name' => 'Refund issued',
            'subject' => 'Refund issued',
            'body' => 'Refund body',
            'channels' => [NotificationChannels::EMAIL, NotificationChannels::IN_APP],
            'variables' => ['amount'],
            'is_active' => true,
        ]);

        $log = NotificationLog::query()->create([
            'user_id' => $customer->id,
            'channel' => NotificationChannels::EMAIL,
            'template_code' => $template->code,
            'event_class' => RefundIssued::class,
            'notification_type' => 'payment',
            'subject' => 'Refund issued',
            'body' => 'Refund body',
            'status' => NotificationLog::STATUS_FAILED,
            'retry_count' => 2,
            'response_payload' => ['error' => 'Gateway timeout'],
            'failed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.notifications.retry', $log, absolute: false))
            ->assertRedirect(route('admin.notifications.index', absolute: false));

        $this->assertSame(NotificationLog::STATUS_PENDING, $log->fresh()->status);
        Queue::assertPushed(SendNotificationChannelJob::class, 1);

        $this->actingAs($admin)
            ->put(route('admin.notifications.templates.update', $template, absolute: false), [
                'name' => 'Refund issued updated',
                'subject' => 'Refund update',
                'body' => 'Updated refund body for {user_name}',
                'channels' => [NotificationChannels::EMAIL],
                'variables' => ['user_name'],
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.notifications.index', absolute: false));

        $template->refresh();

        $this->assertSame('Refund issued updated', $template->name);
        $this->assertSame('Refund update', $template->subject);
        $this->assertSame('Updated refund body for {user_name}', $template->body);
        $this->assertSame([NotificationChannels::EMAIL], $template->channels);
        $this->assertSame(['user_name'], $template->variables);
        $this->assertSame(2, $template->version);
        $this->assertFalse($template->is_active);
    }

    public function test_notification_preferences_can_disable_specific_channels_and_categories(): void
    {
        Queue::fake();

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'phone' => '+966500000099',
        ]);

        $customer->notificationDevices()->create([
            'channel' => NotificationChannels::PUSH,
            'platform' => 'android',
            'device_token' => 'device-token-pref',
            'is_active' => true,
        ]);

        UserNotificationPreference::query()->create([
            'user_id' => $customer->id,
            'email_enabled' => false,
            'in_app_enabled' => true,
            'sms_enabled' => true,
            'push_enabled' => true,
            'whatsapp_enabled' => true,
            'disabled_categories' => ['support'],
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Preferences Provider',
            'booking_reference' => 'BK-NOTIFY-PREF-001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Preferences Suites'],
            'currency' => 'USD',
            'total_amount' => 180.00,
            'request_payload' => ['hotel_name' => 'Preferences Suites'],
        ]);

        app(NotificationService::class)->dispatchForEvent(new OrderConfirmed($order->load('customer')));

        $this->assertDatabaseCount('notification_logs', 2);
        $this->assertDatabaseMissing('notification_logs', [
            'user_id' => $customer->id,
            'channel' => NotificationChannels::EMAIL,
            'template_code' => 'ORDER_CONFIRMED',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'channel' => NotificationChannels::IN_APP,
            'template_code' => 'ORDER_CONFIRMED',
            'template_version' => 1,
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'channel' => NotificationChannels::PUSH,
            'template_code' => 'ORDER_CONFIRMED',
        ]);
    }

    public function test_loyalty_tier_change_notifications_can_be_dispatched_from_extension_event(): void
    {
        Queue::fake();

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'email' => 'loyalty@example.test',
        ]);

        $fromTier = \App\Models\LoyaltyTier::query()->create([
            'code' => 'genius_1',
            'name' => 'Genius 1',
            'level' => 11,
            'sort_order' => 11,
            'is_active' => true,
            'is_default' => true,
        ]);

        $toTier = \App\Models\LoyaltyTier::query()->create([
            'code' => 'genius_2',
            'name' => 'Genius 2',
            'level' => 12,
            'sort_order' => 12,
            'is_active' => true,
            'is_default' => false,
        ]);

        \App\Models\LoyaltyBenefit::query()->create([
            'tier_id' => $toTier->id,
            'code' => 'priority_support',
            'name' => 'Priority Support',
            'description' => 'Priority support access',
            'benefit_type' => 'service',
            'value_type' => 'label',
            'display_order' => 1,
            'is_highlighted' => true,
            'is_active' => true,
        ]);

        $history = \App\Models\LoyaltyHistory::query()->create([
            'user_id' => $customer->id,
            'from_tier_id' => $fromTier->id,
            'to_tier_id' => $toTier->id,
            'action' => \App\Models\LoyaltyHistory::ACTION_UPGRADED,
            'changed_at' => now(),
        ])->fresh()->load(['user', 'fromTier', 'toTier.benefits']);

        app(NotificationService::class)->dispatchForEvent(new LoyaltyTierChanged($history));

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'template_code' => 'LOYALTY_TIER_CHANGED',
            'channel' => NotificationChannels::EMAIL,
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'template_code' => 'LOYALTY_BENEFIT_UNLOCKED',
            'channel' => NotificationChannels::IN_APP,
        ]);
    }

    public function test_financial_reconciliation_dispatches_critical_anomaly_event_for_notification_extension(): void
    {
        Event::fake([CriticalFinanceAnomaliesDetected::class]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Anomaly Provider',
            'booking_reference' => 'BK-NOTIFY-ANOM-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Anomaly Suites'],
            'currency' => 'USD',
            'total_amount' => 100.00,
            'request_payload' => ['hotel_name' => 'Anomaly Suites'],
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '150.00',
            'currency' => 'USD',
            'source' => 'anomaly_payment',
        ]);

        app(FinancialConsistencyService::class)->reconcile(false);

        Event::assertDispatched(CriticalFinanceAnomaliesDetected::class);
    }
}