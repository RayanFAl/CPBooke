<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\FinancialTransaction;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\PriceAlert;
use App\Models\RefreshToken;
use App\Models\TravelSearchIntent;
use App\Models\User;
use App\Models\UserNotification;
use App\Modules\Loyalty\Services\LoyaltyService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_show_returns_empty_loyalty_snapshot_when_profile_has_not_been_initialized(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['super_admin']);

        $this->actingAs($actor)
            ->get(route('admin.users.show', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/pages/Show', false)
                ->where('user.loyalty.current_level', 0)
                ->where('user.loyalty.current_tier', null)
                ->where('user.loyalty.next_tier', null)
                ->where('user.loyalty.progress_to_next_level.percentage', 0)
                ->where('user.loyalty.progress_to_next_level.current_metrics.completed_orders_count', 0)
                ->where('user.loyalty.benefits_unlocked', [])
                ->where('user.loyalty.history', [])
            );
    }

    public function test_user_show_includes_latest_orders_and_financial_transactions_in_descending_order(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $otherCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['super_admin']);

        foreach (range(1, 12) as $index) {
            $order = Order::query()->create([
                'customer_id' => $customer->id,
                'provider_name' => 'Customer View Provider',
                'booking_reference' => sprintf('BK-USER-%02d', $index),
                'status' => Order::STATUS_COMPLETED,
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'service_type' => $index % 2 === 0 ? Order::SERVICE_TYPE_HOTEL : Order::SERVICE_TYPE_FLIGHT,
                'details' => ['index' => $index],
                'currency' => 'USD',
                'total_amount' => (string) (200 + $index),
                'request_payload' => ['index' => $index],
            ]);

            $order->forceFill([
                'created_at' => Carbon::parse('2026-05-07 10:00:00')->addMinutes($index),
                'updated_at' => Carbon::parse('2026-05-07 10:00:00')->addMinutes($index),
            ])->save();

            $transaction = FinancialTransaction::query()->create([
                'order_id' => $order->id,
                'type' => FinancialTransaction::TYPE_PAYMENT,
                'amount' => (string) (200 + $index),
                'currency' => 'USD',
                'source' => sprintf('user_tx_%02d', $index),
            ]);

            $transaction->forceFill([
                'created_at' => Carbon::parse('2026-05-07 11:00:00')->addMinutes($index),
                'updated_at' => Carbon::parse('2026-05-07 11:00:00')->addMinutes($index),
            ])->save();

            $activity = OrderHistory::query()->create([
                'order_id' => $order->id,
                'user_id' => $customer->id,
                'action' => sprintf('activity_%02d', $index),
                'field' => 'status',
                'old_value' => 'pending_payment',
                'new_value' => 'confirmed',
                'created_at' => Carbon::parse('2026-05-07 12:00:00')->addMinutes($index),
            ]);

            $activity->forceFill([
                'created_at' => Carbon::parse('2026-05-07 12:00:00')->addMinutes($index),
            ])->save();
        }

        $otherOrder = Order::query()->create([
            'customer_id' => $otherCustomer->id,
            'provider_name' => 'Other Provider',
            'booking_reference' => 'BK-OTHER-01',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['scope' => 'other'],
            'currency' => 'USD',
            'total_amount' => '999.00',
            'request_payload' => ['scope' => 'other'],
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $otherOrder->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'amount' => '999.00',
            'currency' => 'USD',
            'source' => 'other_customer_tx',
        ]);

        OrderHistory::query()->create([
            'order_id' => $otherOrder->id,
            'user_id' => $otherCustomer->id,
            'action' => 'other_customer_activity',
            'field' => 'status',
            'old_value' => 'pending_payment',
            'new_value' => 'confirmed',
            'created_at' => Carbon::parse('2026-05-08 09:00:00'),
        ]);

        Carbon::setTestNow('2026-05-15 12:00:00');

        app(LoyaltyService::class)->upgradeUserIfEligible($customer);

        $this->actingAs($actor)
            ->get(route('admin.users.show', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/pages/Show', false)
                ->has('user.recent_orders', 10)
                ->has('user.financial_transactions', 10)
                ->has('user.recent_activities', 10)
                ->where('user.recent_orders.0.booking_reference', 'BK-USER-12')
                ->where('user.recent_orders.1.booking_reference', 'BK-USER-11')
                ->where('user.recent_orders.9.booking_reference', 'BK-USER-03')
                ->where('user.financial_transactions.0.source', 'user_tx_12')
                ->where('user.financial_transactions.1.source', 'user_tx_11')
                ->where('user.financial_transactions.9.source', 'user_tx_03')
                ->where('user.recent_activities.0.action', 'activity_12')
                ->where('user.recent_activities.1.action', 'activity_11')
                ->where('user.recent_activities.9.action', 'activity_03')
                ->where('user.loyalty.current_tier.code', 'level_1')
                ->where('user.loyalty.current_level', 1)
                ->where('user.loyalty.next_tier.code', 'level_2')
                ->where('user.loyalty.progress_to_next_level.current_metrics.month_spend', '2478.00')
                ->where('user.loyalty.benefits_unlocked.0.code', 'level_1_discount')
                ->has('user.loyalty.history', 1)
            );

        Carbon::setTestNow();
    }

    public function test_user_show_includes_customer_crm_activity_for_searches_notifications_and_logins(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'last_login_at' => Carbon::parse('2026-08-18 09:00:00'),
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['super_admin']);

        TravelSearchIntent::query()->create([
            'user_id' => $customer->id,
            'origin' => 'MJI',
            'destination' => 'IST',
            'route_key' => TravelSearchIntent::routeKeyFor('MJI', 'IST', '2026-09-01'),
            'departure_date' => '2026-09-01',
            'last_seen_price' => '850.00',
            'currency' => 'LYD',
            'last_searched_at' => Carbon::parse('2026-08-18 08:30:00'),
        ]);

        PriceAlert::query()->create([
            'user_id' => $customer->id,
            'origin' => 'MJI',
            'destination' => 'IST',
            'route_key' => 'mji|ist|2026-09-01',
            'departure_date' => '2026-09-01',
            'target_price' => '700.00',
            'currency' => 'LYD',
            'is_active' => true,
        ]);

        UserNotification::query()->create([
            'user_id' => $customer->id,
            'template_code' => 'LOGIN_ALERT',
            'type' => 'system',
            'title' => 'New login to your account',
            'message' => 'A new sign-in was detected on iPhone.',
            'delivered_at' => Carbon::parse('2026-08-18 09:00:00'),
            'created_at' => Carbon::parse('2026-08-18 09:00:00'),
        ]);

        UserNotification::query()->create([
            'user_id' => $customer->id,
            'template_code' => 'ABANDONED_FLIGHT_SEARCH',
            'type' => 'marketing',
            'title' => 'Still looking at MJI to IST?',
            'message' => 'Complete your booking before the fare changes.',
            'created_at' => Carbon::parse('2026-08-18 10:00:00'),
        ]);

        NotificationLog::query()->create([
            'user_id' => $customer->id,
            'channel' => 'push',
            'template_code' => 'ABANDONED_FLIGHT_SEARCH',
            'subject' => 'Still looking at MJI to IST?',
            'body' => 'Complete your booking before the fare changes.',
            'status' => NotificationLog::STATUS_SENT,
            'sent_at' => Carbon::parse('2026-08-18 10:00:00'),
        ]);

        $customer->createToken('iPhone');

        RefreshToken::query()->create([
            'user_id' => $customer->id,
            'token_hash' => hash('sha256', 'crm-session-token'),
            'device_name' => 'iPhone',
            'remember_me' => false,
            'expires_at' => Carbon::parse('2026-08-25 09:00:00'),
        ]);

        Favorite::factory()->create([
            'user_id' => $customer->id,
            'snapshot' => [
                'title' => 'Tripoli to Istanbul',
                'origin' => 'MJI',
                'destination' => 'IST',
            ],
        ]);

        $this->actingAs($actor)
            ->get(route('admin.users.show', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/pages/Show', false)
                ->where('user.crm.stats.login_count', 1)
                ->where('user.crm.stats.search_count', 1)
                ->where('user.crm.stats.price_alert_count', 1)
                ->where('user.crm.stats.notification_count', 2)
                ->where('user.crm.searches.0.route', 'MJI → IST')
                ->where('user.crm.price_alerts.0.target_price', '700.00')
                ->where('user.crm.notifications.0.template_code', 'ABANDONED_FLIGHT_SEARCH')
                ->where('user.crm.notification_logs.0.channel', 'push')
                ->where('user.crm.session_history.0.device_name', 'iPhone')
                ->where('user.crm.favorites.0.title', 'Tripoli to Istanbul')
                ->has('user.crm.timeline')
                ->where('user.crm.timeline.0.category', fn ($category) => in_array($category, ['search', 'notification', 'login', 'alert', 'account', 'profile'], true))
            );
    }
}