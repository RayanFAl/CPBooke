<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketResolutionReport;
use App\Models\User;
use App\Modules\Admin\Support\Events\SupportMessageBroadcasted;
use App\Modules\Admin\Support\Events\SupportTicketUpdatedBroadcasted;
use App\Modules\Admin\Support\Events\SupportTypingBroadcasted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSupportChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_send_chat_message_and_receive_normalized_payload(): void
    {
        Event::fake([
            SupportMessageBroadcasted::class,
            SupportTicketUpdatedBroadcasted::class,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $agent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-CHAT-1001',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => $agent->id,
            'subject' => 'Live chat follow-up',
            'description' => 'Original chat thread.',
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'message' => 'How can I help?',
            'is_internal' => false,
            'sender_type' => 'agent',
            'message_type' => 'text',
            'delivered_at' => now(),
        ]);

        Sanctum::actingAs($customer);

        $response = $this->postJson("/api/v1/support/chat/tickets/{$ticket->id}/messages", [
            'message' => 'I still need help with this booking.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ticket.id', $ticket->id)
            ->assertJsonPath('data.ticket.code', 'SUP-CHAT-1001')
            ->assertJsonMissingPath('data.ticket.assigned_agent')
            ->assertJsonPath('data.message.sender_type', 'customer')
            ->assertJsonPath('data.message.message_type', 'text')
            ->assertJsonPath('data.message.text', 'I still need help with this booking.');

        Event::assertDispatched(SupportMessageBroadcasted::class);
        Event::assertDispatched(SupportTicketUpdatedBroadcasted::class);
    }

    public function test_customer_can_mark_agent_messages_as_seen_and_clear_unread_count(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $agent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-CHAT-1002',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'open',
            'assigned_to' => $agent->id,
            'subject' => 'Unread counter',
            'description' => 'Unread ticket.',
        ]);

        $message = SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'message' => 'Agent update waiting to be seen.',
            'is_internal' => false,
            'sender_type' => 'agent',
            'message_type' => 'text',
            'delivered_at' => now(),
            'seen_at' => null,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/support/chat/tickets')
            ->assertOk()
            ->assertJsonPath('data.tickets.0.unread_count', 1);

        $this->postJson("/api/v1/support/chat/tickets/{$ticket->id}/seen", [
            'message_ids' => [$message->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.seen_count', 1)
            ->assertJsonPath('data.ticket.unread_count', 0);

        $this->assertNotNull($message->fresh()->seen_at);
    }

    public function test_customer_can_send_chat_pdf_attachment_without_text(): void
    {
        Storage::fake('public');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-CHAT-1002A',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'document_request',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => null,
            'subject' => 'Attachment only pdf',
            'description' => 'Attachment only message should be accepted.',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->post("/api/v1/support/chat/tickets/{$ticket->id}/messages", [
            'attachment' => UploadedFile::fake()->create('ticket-copy.pdf', 120, 'application/pdf'),
            'metadata' => ['source' => 'mobile-app'],
        ], ['Accept' => 'application/json']);

        $response
            ->assertOk()
            ->assertJsonPath('data.message.message_type', 'file')
            ->assertJsonPath('data.message.attachment.name', 'ticket-copy.pdf')
            ->assertJsonPath('data.message.attachment.mime', 'application/pdf')
            ->assertJsonPath('data.message.attachment.is_image', false)
            ->assertJsonPath('data.message.attachment.is_video', false);

        Storage::disk('public')->assertExists(SupportMessage::query()->latest('id')->firstOrFail()->attachment_path);
    }

    public function test_customer_can_send_chat_video_attachment_without_text(): void
    {
        Storage::fake('public');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-CHAT-1002B',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => null,
            'subject' => 'Attachment only video',
            'description' => 'Video attachment should be accepted.',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->post("/api/v1/support/chat/tickets/{$ticket->id}/messages", [
            'attachment' => UploadedFile::fake()->create('screen-recording.mp4', 1024, 'video/mp4'),
            'metadata' => ['source' => 'mobile-app'],
        ], ['Accept' => 'application/json']);

        $response
            ->assertOk()
            ->assertJsonPath('data.message.message_type', 'video')
            ->assertJsonPath('data.message.attachment.name', 'screen-recording.mp4')
            ->assertJsonPath('data.message.attachment.mime', 'video/mp4')
            ->assertJsonPath('data.message.attachment.is_image', false)
            ->assertJsonPath('data.message.attachment.is_video', true);

        Storage::disk('public')->assertExists(SupportMessage::query()->latest('id')->firstOrFail()->attachment_path);
    }

    public function test_typing_endpoint_dispatches_transport_event_without_mutating_ticket_behavior(): void
    {
        Event::fake([SupportTypingBroadcasted::class]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-CHAT-1003',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'document_request',
            'priority' => 'low',
            'status' => 'waiting_customer',
            'assigned_to' => null,
            'subject' => 'Typing state',
            'description' => 'Typing should not change status.',
        ]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/support/chat/tickets/{$ticket->id}/typing", [
            'typing' => true,
            'metadata' => ['source' => 'flutter'],
        ])
            ->assertOk()
            ->assertJsonPath('data.is_typing', true)
            ->assertJsonPath('data.typing.sender_type', 'customer')
            ->assertJsonPath('data.typing.metadata.source', 'flutter')
            ->assertJsonPath('data.ticket.status', 'waiting_customer');

        Event::assertDispatched(SupportTypingBroadcasted::class);
        $this->assertSame('waiting_customer', $ticket->fresh()->status);
    }

    public function test_typing_endpoint_accepts_agent_typing_alias_on_post(): void
    {
        Event::fake([SupportTypingBroadcasted::class]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-CHAT-1003B',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'document_request',
            'priority' => 'low',
            'status' => 'waiting_customer',
            'assigned_to' => null,
            'subject' => 'Typing alias',
            'description' => 'agent_typing should behave like typing.',
        ]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/support/chat/tickets/{$ticket->id}/typing", [
            'agent_typing' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_typing', true)
            ->assertJsonPath('data.typing.typing', true);

        Event::assertDispatched(SupportTypingBroadcasted::class);
    }

    public function test_get_typing_endpoint_returns_agent_typing_state(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $agent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-CHAT-1003C',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'document_request',
            'priority' => 'low',
            'status' => 'in_progress',
            'assigned_to' => $agent->id,
            'subject' => 'Agent typing poll',
            'description' => 'Customer polls agent typing state.',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/support/chat/tickets/{$ticket->id}/typing")
            ->assertOk()
            ->assertJsonPath('data.is_typing', false);

        $this->app->make(\App\Modules\Support\Services\SupportService::class)->storeTypingState(
            $ticket,
            $agent,
            true,
        );

        $this->getJson("/api/v1/support/chat/tickets/{$ticket->id}/typing")
            ->assertOk()
            ->assertJsonPath('data.is_typing', true);
    }

    public function test_get_typing_endpoint_accepts_typing_and_agent_typing_query_aliases(): void
    {
        Event::fake([SupportTypingBroadcasted::class]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-CHAT-1003D',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'document_request',
            'priority' => 'low',
            'status' => 'waiting_customer',
            'assigned_to' => null,
            'subject' => 'Typing query aliases',
            'description' => 'GET typing query params.',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/support/chat/tickets/{$ticket->id}/typing?typing=1")
            ->assertOk()
            ->assertJsonPath('data.is_typing', true);

        Event::assertDispatched(SupportTypingBroadcasted::class);

        Event::fake([SupportTypingBroadcasted::class]);

        $this->getJson("/api/v1/support/chat/tickets/{$ticket->id}/typing?agent_typing=0")
            ->assertOk()
            ->assertJsonPath('data.is_typing', false);

        Event::assertDispatched(SupportTypingBroadcasted::class);
    }

    public function test_chat_message_endpoint_does_not_reopen_closed_ticket_incorrectly(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-CHAT-1004',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'closed',
            'assigned_to' => null,
            'subject' => 'Closed thread',
            'description' => 'Closed ticket should remain closed.',
            'closed_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/support/chat/tickets/{$ticket->id}/messages", [
            'message' => 'Late follow-up on a closed chat.',
        ])
            ->assertOk()
            ->assertJsonPath('data.ticket.status', 'closed');

        $ticket->refresh();

        $this->assertSame('closed', $ticket->status);
    }

    public function test_chat_endpoints_keep_resolution_report_compatible_when_resolved_ticket_reopens(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $agent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-CHAT-1005',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'resolved',
            'assigned_to' => $agent->id,
            'subject' => 'Resolved chat thread',
            'description' => 'Resolved thread should reopen safely.',
            'resolved_at' => now()->subMinutes(30),
        ]);

        $report = SupportTicketResolutionReport::query()->create([
            'ticket_id' => $ticket->id,
            'agent_id' => $agent->id,
            'resolution_type' => 'resolved',
            'root_cause' => 'Initial issue fixed.',
            'actions_taken' => 'Reconciled the booking data.',
            'resolution_summary' => 'Issue marked resolved before reopen.',
            'status_before' => 'in_progress',
            'status_after' => 'resolved',
            'handling_minutes' => 15,
            'escalated' => false,
            'reopened_count' => 0,
            'satisfaction_requested' => false,
            'metadata' => ['source' => 'support_resolution_report'],
            'resolved_at' => now()->subMinutes(30),
        ]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/support/chat/tickets/{$ticket->id}/messages", [
            'message' => 'The same issue came back.',
        ])
            ->assertOk()
            ->assertJsonPath('data.ticket.status', 'open');

        $ticket->refresh();

        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertDatabaseHas('support_ticket_resolution_reports', [
            'id' => $report->id,
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_chat_messages_endpoint_supports_pagination(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-CHAT-1006',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'document_request',
            'priority' => 'low',
            'status' => 'open',
            'assigned_to' => null,
            'subject' => 'Pagination chat',
            'description' => 'Paginated thread.',
        ]);

        foreach (range(1, 3) as $index) {
            SupportMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $customer->id,
                'message' => 'Message '.$index,
                'is_internal' => false,
                'sender_type' => 'customer',
                'message_type' => 'text',
                'delivered_at' => now(),
            ]);
        }

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/support/chat/tickets/{$ticket->id}/messages?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }
}