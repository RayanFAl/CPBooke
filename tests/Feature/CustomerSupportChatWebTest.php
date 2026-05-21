<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerSupportChatWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_customer_to_web_support_chat(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->actingAs($customer)
            ->get('/')
            ->assertRedirect(route('customer.support.chat', absolute: false));
    }

    public function test_customer_can_view_support_chat_page(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->actingAs($customer)
            ->get(route('customer.support.chat', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Support/Chat')
                ->where('chat.routes.tickets_index', '/api/v1/support/chat/tickets')
                ->where('chat.routes.tickets_create_or_reuse', '/api/v1/support/tickets/messages')
            );
    }

    public function test_admin_is_redirected_away_from_customer_support_chat_page(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('customer.support.chat', absolute: false))
            ->assertRedirect(route('admin.support.index', absolute: false));
    }

    public function test_web_authenticated_customer_can_consume_chat_api_without_personal_access_token(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-WEB-1001',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => null,
            'subject' => 'Web session chat API',
            'description' => 'The web session should consume the same chat API.',
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'message' => 'Initial web message.',
            'is_internal' => false,
            'sender_type' => 'customer',
            'message_type' => 'text',
            'delivered_at' => now(),
        ]);

        $this->actingAs($customer)
            ->getJson('/api/v1/support/chat/tickets')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tickets.0.id', $ticket->id);
    }
}