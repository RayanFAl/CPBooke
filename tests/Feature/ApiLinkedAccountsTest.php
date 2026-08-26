<?php

namespace Tests\Feature;

use App\Models\LinkedAccount;
use App\Models\LinkedAccountRequest;
use App\Models\User;
use App\Modules\Notifications\Events\PassengerActionDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiLinkedAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'is_active' => true,
        ], $overrides));
    }

    public function test_customer_can_list_linked_accounts(): void
    {
        $customer = $this->customer();
        $linked = $this->customer([
            'name' => 'Ahmed Ali',
            'full_name' => 'Ahmed Ali',
            'phone' => '+966501111111',
        ]);

        LinkedAccount::factory()->create([
            'user_id' => $customer->id,
            'linked_user_id' => $linked->id,
            'relationship_type' => LinkedAccount::RELATIONSHIP_PARENT,
            'nickname' => 'أبي',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/linked-accounts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.linked_user_id', (string) $linked->id)
            ->assertJsonPath('data.0.relationship_type', 'parent')
            ->assertJsonPath('data.0.nickname', 'أبي')
            ->assertJsonPath('data.0.linked_user_name', 'Ahmed Ali')
            ->assertJsonPath('data.0.linked_user_phone', '+966501111111');
    }

    public function test_customer_can_send_link_request_by_phone(): void
    {
        Event::fake([PassengerActionDue::class]);

        $from = $this->customer(['full_name' => 'Sender Name']);
        $to = $this->customer(['phone' => '+966501234567']);

        Sanctum::actingAs($from);

        $response = $this->postJson('/api/v1/linked-accounts/requests', [
            'to_user' => '+966501234567',
            'relationship_type' => 'parent',
            'nickname' => 'أبي',
            'message' => 'Please link',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.from_user_id', (string) $from->id)
            ->assertJsonPath('data.to_user_id', (string) $to->id)
            ->assertJsonPath('data.relationship_type', 'parent')
            ->assertJsonPath('data.nickname', 'أبي')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('linked_account_requests', [
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'status' => LinkedAccountRequest::STATUS_PENDING,
        ]);

        Event::assertDispatched(PassengerActionDue::class, function (PassengerActionDue $event) use ($from, $to): bool {
            return $event->code === 'LINK_REQUEST_RECEIVED'
                && $event->user->is($to)
                && ($event->payload['sender_name'] ?? null) === 'Sender Name'
                && ($event->payload['deep_link'] ?? null) === '/linked-accounts'
                && ($event->payload['from_user_id'] ?? null) === (string) $from->id;
        });
    }

    public function test_customer_cannot_link_themselves(): void
    {
        $customer = $this->customer(['phone' => '+966509999999']);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/linked-accounts/requests', [
            'to_user' => '+966509999999',
            'relationship_type' => 'friend',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.to_user.0', 'You cannot link your own account.');
    }

    public function test_duplicate_pending_request_is_rejected(): void
    {
        $from = $this->customer();
        $to = $this->customer(['phone' => '+966501234567']);

        LinkedAccountRequest::factory()->create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'status' => LinkedAccountRequest::STATUS_PENDING,
        ]);

        Sanctum::actingAs($from);

        $this->postJson('/api/v1/linked-accounts/requests', [
            'to_user' => '+966501234567',
            'relationship_type' => 'friend',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.to_user.0', 'A pending link request already exists for this user.');
    }

    public function test_recipient_only_sees_incoming_requests(): void
    {
        $from = $this->customer(['name' => 'Sender', 'full_name' => 'Sender']);
        $to = $this->customer();
        $other = $this->customer();

        $incoming = LinkedAccountRequest::factory()->create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'status' => LinkedAccountRequest::STATUS_PENDING,
            'nickname' => 'Friend',
        ]);

        LinkedAccountRequest::factory()->create([
            'from_user_id' => $to->id,
            'to_user_id' => $other->id,
            'status' => LinkedAccountRequest::STATUS_PENDING,
        ]);

        Sanctum::actingAs($to);

        $this->getJson('/api/v1/linked-accounts/requests?status=pending')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $incoming->id)
            ->assertJsonPath('data.0.from_user_name', 'Sender');
    }

    public function test_recipient_can_accept_request_and_both_sides_are_created(): void
    {
        Event::fake([PassengerActionDue::class]);

        $from = $this->customer();
        $to = $this->customer(['full_name' => 'Accepter']);

        $linkRequest = LinkedAccountRequest::factory()->create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'relationship_type' => LinkedAccount::RELATIONSHIP_PARENT,
            'nickname' => 'أبي',
            'status' => LinkedAccountRequest::STATUS_PENDING,
        ]);

        Sanctum::actingAs($to);

        $this->postJson("/api/v1/linked-accounts/requests/{$linkRequest->id}/respond", [
            'accept' => true,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('linked_account_requests', [
            'id' => $linkRequest->id,
            'status' => LinkedAccountRequest::STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseHas('linked_accounts', [
            'user_id' => $from->id,
            'linked_user_id' => $to->id,
            'relationship_type' => 'parent',
            'nickname' => 'أبي',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('linked_accounts', [
            'user_id' => $to->id,
            'linked_user_id' => $from->id,
            'relationship_type' => 'child',
            'is_active' => true,
        ]);

        Event::assertDispatched(PassengerActionDue::class, function (PassengerActionDue $event) use ($from): bool {
            return $event->code === 'LINK_REQUEST_ACCEPTED'
                && $event->user->is($from)
                && ($event->payload['recipient_name'] ?? null) === 'Accepter'
                && ($event->payload['deep_link'] ?? null) === '/linked-accounts';
        });
    }

    public function test_recipient_can_reject_request(): void
    {
        Event::fake([PassengerActionDue::class]);

        $from = $this->customer();
        $to = $this->customer(['full_name' => 'Rejecter']);

        $linkRequest = LinkedAccountRequest::factory()->create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'status' => LinkedAccountRequest::STATUS_PENDING,
        ]);

        Sanctum::actingAs($to);

        $this->postJson("/api/v1/linked-accounts/requests/{$linkRequest->id}/respond", [
            'accept' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseCount('linked_accounts', 0);

        Event::assertDispatched(PassengerActionDue::class, function (PassengerActionDue $event) use ($from): bool {
            return $event->code === 'LINK_REQUEST_REJECTED'
                && $event->user->is($from)
                && ($event->payload['recipient_name'] ?? null) === 'Rejecter';
        });
    }

    public function test_non_recipient_cannot_respond_to_request(): void
    {
        $from = $this->customer();
        $to = $this->customer();
        $stranger = $this->customer();

        $linkRequest = LinkedAccountRequest::factory()->create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'status' => LinkedAccountRequest::STATUS_PENDING,
        ]);

        Sanctum::actingAs($stranger);

        $this->postJson("/api/v1/linked-accounts/requests/{$linkRequest->id}/respond", [
            'accept' => true,
        ])->assertForbidden();
    }

    public function test_customer_can_update_permissions(): void
    {
        $customer = $this->customer();
        $linked = $this->customer();

        $account = LinkedAccount::factory()->create([
            'user_id' => $customer->id,
            'linked_user_id' => $linked->id,
            'can_request_payment' => false,
            'can_receive_payment_requests' => true,
            'auto_approve' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->patchJson("/api/v1/linked-accounts/{$account->id}/permissions", [
            'can_request_payment' => true,
            'auto_approve' => true,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.can_request_payment', true)
            ->assertJsonPath('data.can_receive_payment_requests', true)
            ->assertJsonPath('data.auto_approve', true);
    }

    public function test_customer_can_delete_linked_account_on_both_sides(): void
    {
        $customer = $this->customer();
        $linked = $this->customer();

        $account = LinkedAccount::factory()->create([
            'user_id' => $customer->id,
            'linked_user_id' => $linked->id,
        ]);

        LinkedAccount::factory()->create([
            'user_id' => $linked->id,
            'linked_user_id' => $customer->id,
        ]);

        Sanctum::actingAs($customer);

        $this->deleteJson("/api/v1/linked-accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('linked_accounts', 0);
    }

    public function test_customer_cannot_update_another_users_link(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();
        $linked = $this->customer();

        $account = LinkedAccount::factory()->create([
            'user_id' => $owner->id,
            'linked_user_id' => $linked->id,
        ]);

        Sanctum::actingAs($stranger);

        $this->patchJson("/api/v1/linked-accounts/{$account->id}/permissions", [
            'can_request_payment' => true,
        ])->assertForbidden();
    }

    public function test_customer_can_search_user_by_email(): void
    {
        $actor = $this->customer();
        $target = $this->customer([
            'email' => 'linkme@example.com',
            'name' => 'Target User',
            'full_name' => 'Target User',
            'phone' => '+966502222222',
        ]);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/linked-accounts/search', [
            'identifier' => 'linkme@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', (string) $target->id)
            ->assertJsonPath('data.name', 'Target User')
            ->assertJsonPath('data.phone', '+966502222222')
            ->assertJsonPath('data.email', 'linkme@example.com');
    }

    public function test_search_returns_404_when_user_missing(): void
    {
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/v1/linked-accounts/search', [
            'identifier' => 'missing@example.com',
        ])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'not_found');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/linked-accounts')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }
}
