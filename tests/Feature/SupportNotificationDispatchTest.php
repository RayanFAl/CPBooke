<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Admin\Support\Events\SupportTicketCreated;
use App\Modules\Admin\Support\Events\SupportTicketStatusChanged;
use App\Modules\Notifications\Jobs\SendNotificationChannelJob;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Support\NotificationChannels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SupportNotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_status_change_dispatches_customer_notifications(): void
    {
        Queue::fake();

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'email' => 'support-notify@example.test',
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-NOTIFY-001',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => null,
            'subject' => 'Need status update',
            'description' => 'Please close when done.',
        ]);

        app(NotificationService::class)->dispatchForEvent(
            new SupportTicketStatusChanged($ticket->load('user'), 'open', 'closed')
        );

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'channel' => NotificationChannels::IN_APP,
            'template_code' => 'SUPPORT_TICKET_CLOSED',
            'status' => NotificationLog::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'channel' => NotificationChannels::EMAIL,
            'template_code' => 'SUPPORT_TICKET_CLOSED',
            'status' => NotificationLog::STATUS_PENDING,
        ]);

        Queue::assertPushed(SendNotificationChannelJob::class, 2);
    }

    public function test_support_ticket_created_notifies_customer_without_duplicate_agent_created_code(): void
    {
        Queue::fake();

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $agent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-NOTIFY-002',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'refund_request',
            'priority' => 'high',
            'status' => 'open',
            'assigned_to' => $agent->id,
            'subject' => 'Refund help',
            'description' => 'Need a refund review.',
        ]);
        $ticket->setRelation('user', $customer);
        $ticket->setRelation('assignee', $agent);

        app(NotificationService::class)->dispatchForEvent(new SupportTicketCreated($ticket));

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'template_code' => 'SUPPORT_TICKET_CREATED_CUSTOMER',
        ]);
        $this->assertDatabaseMissing('notification_logs', [
            'template_code' => 'SUPPORT_TICKET_CREATED_AGENT',
        ]);
    }
}
