<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_list_show_and_reply_to_support_tickets(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $order = $this->createOrderForCustomer($customer, 'BK-SUP-001');

        Sanctum::actingAs($customer);

        $createResponse = $this->postJson('/api/v1/support/tickets', [
            'order_id' => $order->id,
            'category' => 'payment_issue',
            'priority' => 'high',
            'subject' => 'Payment captured but voucher missing',
            'message' => 'I completed checkout and did not receive my voucher.',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ticket.user.id', $customer->id)
            ->assertJsonPath('data.ticket.order.id', $order->id)
            ->assertJsonPath('data.ticket.messages.0.sender_type', 'user')
            ->assertJsonPath('data.ticket.status', 'open');

        $ticketId = (int) $createResponse->json('data.ticket.id');

        $this->getJson('/api/v1/support/tickets')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.tickets')
            ->assertJsonPath('data.tickets.0.id', $ticketId);

        $this->getJson("/api/v1/support/tickets/{$ticketId}")
            ->assertOk()
            ->assertJsonPath('data.ticket.id', $ticketId)
            ->assertJsonPath('data.ticket.messages.0.message', 'I completed checkout and did not receive my voucher.');

        $this->getJson('/api/v1/support/tickets/current?order_id='.$order->id)
            ->assertOk()
            ->assertJsonPath('data.ticket.id', $ticketId)
            ->assertJsonPath('data.ticket.order.id', $order->id);

        $this->postJson('/api/v1/support/tickets/messages', [
            'order_id' => $order->id,
            'category' => 'payment_issue',
            'priority' => 'high',
            'subject' => 'Payment captured but voucher missing',
            'message' => 'Sharing another update in the same conversation.',
        ])
            ->assertOk()
            ->assertJsonPath('data.ticket.id', $ticketId)
            ->assertJsonPath('data.ticket.messages.1.message', 'Sharing another update in the same conversation.')
            ->assertJsonPath('data.ticket.messages.1.sender_type', 'user');

        $this->assertSame(1, SupportTicket::query()->count());

        $this->postJson("/api/v1/support/tickets/{$ticketId}/reply", [
            'message' => 'Any update on this issue?',
        ])
            ->assertOk()
            ->assertJsonPath('data.ticket.id', $ticketId)
            ->assertJsonPath('data.ticket.messages.2.message', 'Any update on this issue?')
            ->assertJsonPath('data.ticket.messages.2.sender_type', 'user');
    }

    public function test_customer_message_reopens_waiting_customer_and_resolved_tickets_but_preserves_closed_ticket(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $waitingTicket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-REOPEN-1001',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'waiting_customer',
            'assigned_to' => null,
            'subject' => 'Need portal update',
            'description' => 'Waiting for customer input.',
        ]);

        $resolvedTicket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-REOPEN-1002',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'resolved',
            'assigned_to' => null,
            'subject' => 'Voucher still missing',
            'description' => 'Marked resolved earlier.',
            'resolved_at' => now()->subHour(),
        ]);

        $closedTicket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-REOPEN-1003',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'document_request',
            'priority' => 'low',
            'status' => 'closed',
            'assigned_to' => null,
            'subject' => 'Closed passport thread',
            'description' => 'Closed ticket should stay closed.',
            'closed_at' => now()->subDay(),
        ]);

        $this->postJson('/api/v1/support/tickets/messages', [
            'category' => 'technical_issue',
            'priority' => 'medium',
            'subject' => 'Need portal update',
            'message' => 'Following up from the customer side.',
        ])
            ->assertOk()
            ->assertJsonPath('data.ticket.id', $waitingTicket->id)
            ->assertJsonPath('data.ticket.status', 'open');

        $waitingTicket->refresh();

        $this->assertSame('open', $waitingTicket->status);

        $this->postJson('/api/v1/support/tickets/messages', [
            'category' => 'payment_issue',
            'priority' => 'high',
            'subject' => 'Voucher still missing',
            'message' => 'The issue is still happening.',
        ])
            ->assertOk()
            ->assertJsonPath('data.ticket.id', $resolvedTicket->id)
            ->assertJsonPath('data.ticket.status', 'open');

        $resolvedTicket->refresh();

        $this->assertSame('open', $resolvedTicket->status);
        $this->assertNull($resolvedTicket->resolved_at);

        $this->postJson("/api/v1/support/tickets/{$closedTicket->id}/reply", [
            'message' => 'Adding a late follow-up to a closed ticket.',
        ])
            ->assertOk()
            ->assertJsonPath('data.ticket.id', $closedTicket->id)
            ->assertJsonPath('data.ticket.status', 'closed');

        $closedTicket->refresh();

        $this->assertSame('closed', $closedTicket->status);

        $this->postJson('/api/v1/support/tickets/messages', [
            'category' => 'document_request',
            'priority' => 'low',
            'subject' => 'Closed passport thread',
            'message' => 'Start a fresh conversation for the closed case.',
        ])
            ->assertCreated();

        $this->assertSame(4, SupportTicket::query()->count());
    }

    public function test_customer_can_send_image_attachment_and_read_attachment_metadata(): void
    {
        Storage::fake('local');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $image = UploadedFile::fake()->image('voucher.png');

        $response = $this->post('/api/v1/support/tickets/messages', [
            'category' => 'document_request',
            'priority' => 'medium',
            'subject' => 'Upload travel voucher',
            'message' => 'Attaching the travel voucher image.',
            'attachment' => $image,
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.ticket.messages.0.attachment_name', 'voucher.png')
            ->assertJsonPath('data.ticket.messages.0.attachment_mime', 'image/png')
            ->assertJsonPath('data.ticket.messages.0.attachment_is_image', true)
            ->assertJsonMissingPath('data.ticket.messages.0.attachment_path');

        $message = SupportMessage::query()->firstOrFail();

        Storage::disk('local')->assertExists($message->attachment_path);

        $this->getJson('/api/v1/support/tickets/current?subject=Upload%20travel%20voucher')
            ->assertOk()
            ->assertJsonMissingPath('data.ticket.messages.0.attachment_path')
            ->assertJsonPath('data.ticket.messages.0.attachment_name', 'voucher.png')
            ->assertJsonPath('data.ticket.messages.0.attachment_mime', 'image/png')
            ->assertJsonPath('data.ticket.messages.0.attachment_size', $message->attachment_size);

        $attachmentUrl = $this->getJson('/api/v1/support/tickets/current?subject=Upload%20travel%20voucher')
            ->json('data.ticket.messages.0.attachment_url');

        $this->assertIsString($attachmentUrl);
        $this->assertStringContainsString('/support/attachments/'.$message->id, $attachmentUrl);

        $this->get($attachmentUrl)->assertOk();
    }

    public function test_customer_can_send_file_attachment_and_read_attachment_metadata(): void
    {
        Storage::fake('local');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $file = UploadedFile::fake()->create('itinerary.pdf', 120, 'application/pdf');

        $response = $this->post('/api/v1/support/tickets/messages', [
            'category' => 'document_request',
            'priority' => 'medium',
            'subject' => 'Need itinerary copy',
            'message' => 'Please find the itinerary PDF attached.',
            'attachment' => $file,
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.ticket.messages.0.attachment_name', 'itinerary.pdf')
            ->assertJsonPath('data.ticket.messages.0.attachment_mime', 'application/pdf')
            ->assertJsonPath('data.ticket.messages.0.attachment_is_image', false)
            ->assertJsonMissingPath('data.ticket.messages.0.attachment_path');

        $message = SupportMessage::query()->firstOrFail();

        Storage::disk('local')->assertExists($message->attachment_path);

        $this->getJson('/api/v1/support/tickets/current?subject=Need%20itinerary%20copy')
            ->assertOk()
            ->assertJsonMissingPath('data.ticket.messages.0.attachment_path')
            ->assertJsonPath('data.ticket.messages.0.attachment_name', 'itinerary.pdf')
            ->assertJsonPath('data.ticket.messages.0.attachment_mime', 'application/pdf')
            ->assertJsonPath('data.ticket.messages.0.attachment_size', $message->attachment_size);
    }

    public function test_customer_cannot_access_foreign_support_tickets_or_link_foreign_orders(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $otherCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $foreignOrder = $this->createOrderForCustomer($otherCustomer, 'BK-FOREIGN-001');

        $foreignTicket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-API-0001',
            'user_id' => $otherCustomer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => null,
            'subject' => 'Foreign ticket',
            'description' => 'Should not be visible to another customer.',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/support/tickets/{$foreignTicket->id}")
            ->assertForbidden();

        $this->postJson("/api/v1/support/tickets/{$foreignTicket->id}/reply", [
            'message' => 'Unauthorized reply',
        ])->assertForbidden();

        $this->postJson('/api/v1/support/tickets', [
            'order_id' => $foreignOrder->id,
            'category' => 'payment_issue',
            'priority' => 'low',
            'subject' => 'Invalid foreign order link',
            'message' => 'This should fail.',
        ])->assertForbidden();
    }

    public function test_admin_account_cannot_use_customer_support_api(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/support/tickets')->assertForbidden();
        $this->postJson('/api/v1/support/tickets', [
            'category' => 'technical_issue',
            'priority' => 'medium',
            'subject' => 'Blocked admin request',
            'message' => 'Admins should not use this customer endpoint.',
        ])->assertForbidden();
    }

    /**
     * Create an order linked to the supplied customer.
     */
    private function createOrderForCustomer(User $customer, string $bookingReference): Order
    {
        return Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Support API Provider',
            'external_booking_id' => 'EXT-'.$bookingReference,
            'booking_reference' => $bookingReference,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['route' => 'RUH-JED'],
            'currency' => 'USD',
            'total_amount' => '220.00',
            'internal_notes' => null,
            'request_payload' => ['route' => 'RUH-JED'],
            'response_payload' => null,
            'error_message' => null,
        ]);
    }
}