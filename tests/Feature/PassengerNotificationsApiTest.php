<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassengerNotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_supports_unread_filter_and_mobile_payload(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Already read',
            'message' => 'Read item',
            'read_at' => now(),
            'delivered_at' => now(),
        ]);

        $unread = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'order',
            'title' => 'Booking Confirmed',
            'message' => 'Your flight booking has been confirmed',
            'data' => [
                'variables' => [
                    'order_id' => 99,
                    'service_type' => 'flight',
                ],
            ],
            'related_type' => 'order',
            'related_id' => 99,
            'delivered_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications?page=1&per_page=15&unread_only=false')
            ->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.id', (string) $unread->id)
            ->assertJsonPath('data.0.body', 'Your flight booking has been confirmed')
            ->assertJsonPath('data.0.deep_link', '/orders/99')
            ->assertJsonPath('data.0.meta.product_type', 'flight')
            ->assertJsonPath('data.0.is_read', false);

        $this->getJson('/api/v1/notifications?unread_only=true')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_mark_one_read_all_and_delete_flow(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $first = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'payment',
            'title' => 'Payment',
            'message' => 'Paid',
            'delivered_at' => now(),
        ]);

        $second = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'System',
            'message' => 'Hello',
            'delivered_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/'.$first->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.id', (string) $first->id)
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($first->fresh()->read_at);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertNotNull($second->fresh()->read_at);

        $this->deleteJson('/api/v1/notifications/'.$first->id)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('user_notifications', ['id' => $first->id]);

        $this->postJson('/api/v1/notifications/clear')
            ->assertOk()
            ->assertJsonPath('data.deleted', 1);

        $this->assertSame(0, UserNotification::query()->where('user_id', $user->id)->count());
    }

    public function test_device_registration_and_simulated_push_test(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/devices', [
            'device_token' => 'fcm-test-token-1234567890',
            'platform' => 'android',
        ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/v1/notifications/push-test', [
            'title' => 'Hello Push',
            'body' => 'Testing push channel',
        ])
            ->assertOk()
            ->assertJsonPath('data.notification.title', 'Hello Push')
            ->assertJsonPath('data.push.provider', 'push-simulated')
            ->assertJsonPath('data.push.delivered', true)
            ->assertJsonPath('data.push.tokens_count', 1);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    public function test_push_test_without_device_reports_missing_device(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/push-test')
            ->assertOk()
            ->assertJsonPath('data.push.delivered', false)
            ->assertJsonPath('data.push.reason', 'missing_device');
    }
}
