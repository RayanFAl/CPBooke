<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyHistory;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTier;
use App\Models\Order;
use App\Models\User;
use App\Modules\Loyalty\Listeners\RecalculateUserLoyaltyListener;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\PaymentSucceeded;
use App\Modules\Orders\Events\RefundIssued;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltySystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_loyalty_service_upgrades_user_and_applies_benefits_without_points_wallet_logic(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        foreach (range(1, 15) as $index) {
            $this->createQualifiedOrder(
                $customer,
                300.00,
                Carbon::parse('2026-05-01 10:00:00')->subDays($index),
            );
        }

        $service = app(LoyaltyService::class);
        $profile = $service->upgradeUserIfEligible($customer);
        $orderPreview = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Loyalty Preview Provider',
            'booking_reference' => 'BK-LOYALTY-PREVIEW',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_HOTEL,
            'details' => ['hotel_name' => 'Preview Suites'],
            'currency' => 'USD',
            'total_amount' => 500.00,
            'request_payload' => ['hotel_name' => 'Preview Suites'],
        ]);

        $application = $service->applyBenefitsToOrder($orderPreview->load('customer'));

        $this->assertSame('active', $profile->currentTier?->code);
        $this->assertSame('vip', $profile->nextTier?->code);
        $this->assertSame(15, $profile->completed_orders_count);
        $this->assertSame('4500.00', number_format((float) $profile->lifetime_spend, 2, '.', ''));
        $this->assertGreaterThan(0, $profile->progress_percentage);
        $this->assertSame('50.00', $application['pricing']['discount_amount']);
        $this->assertSame('450.00', $application['pricing']['final_total']);
        $this->assertContains('priority_support', $application['service_flags']);
        $this->assertDatabaseHas('loyalty_history', [
            'user_id' => $customer->id,
            'action' => LoyaltyHistory::ACTION_UPGRADED,
            'to_tier_id' => $profile->current_tier_id,
        ]);
        $this->assertDatabaseMissing('loyalty_history', [
            'notes' => 'points',
        ]);
    }

    public function test_profile_api_exposes_current_level_progress_benefits_and_upgrade_history(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        foreach (range(1, 5) as $index) {
            $this->createQualifiedOrder(
                $customer,
                250.00,
                Carbon::parse('2026-05-10 10:00:00')->subDays($index),
            );
        }

        app(LoyaltyService::class)->upgradeUserIfEligible($customer);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/users/profile')
            ->assertOk()
            ->assertJsonPath('data.user.loyalty.current_tier.code', 'regular')
            ->assertJsonPath('data.user.loyalty.current_level', 1)
            ->assertJsonPath('data.user.loyalty.next_tier.code', 'active')
            ->assertJsonPath('data.user.loyalty.progress_to_next_level.current_metrics.completed_orders_count', 5)
            ->assertJsonPath('data.user.loyalty.benefits_unlocked.0.code', 'discount_5_percent')
            ->assertJsonCount(1, 'data.user.loyalty.history');
    }

    public function test_admin_can_view_loyalty_dashboard_and_update_tiers_rules_and_benefits(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        foreach (range(1, 5) as $index) {
            $this->createQualifiedOrder(
                $customer,
                250.00,
                Carbon::parse('2026-05-15 10:00:00')->subDays($index),
            );
        }

        app(LoyaltyService::class)->upgradeUserIfEligible($customer);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        $tier = LoyaltyTier::query()->where('code', 'regular')->firstOrFail();
        $rule = LoyaltyRule::query()->where('tier_id', $tier->id)->firstOrFail();
        $benefit = LoyaltyBenefit::query()->where('tier_id', $tier->id)->where('code', 'discount_5_percent')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.loyalty.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/loyalty/pages/Index', false)
                ->where('dashboard.metrics.profiles', 1)
                ->where('dashboard.tiers.1.code', 'regular')
                ->where('dashboard.users_per_tier.1.users.0.user.id', $customer->id)
            );

        $this->actingAs($admin)
            ->put(route('admin.loyalty.tiers.update', $tier, absolute: false), [
                'code' => 'regular',
                'name' => 'Regular Plus',
                'description' => 'Adjusted tier naming for admin validation.',
                'badge_label' => 'Level 1+',
                'color_token' => 'emerald',
                'sort_order' => 1,
                'is_active' => true,
                'is_default' => false,
            ])
            ->assertRedirect(route('admin.loyalty.index', absolute: false));

        $this->actingAs($admin)
            ->put(route('admin.loyalty.rules.update', $rule, absolute: false), [
                'name' => 'Regular tier adjusted rule',
                'rule_type' => LoyaltyRule::TYPE_UPGRADE,
                'min_completed_orders' => 4,
                'min_lifetime_spend' => 900,
                'min_period_orders' => 2,
                'min_period_spend' => 400,
                'period_days' => 365,
                'allow_downgrade' => false,
                'is_active' => true,
                'priority' => 1,
            ])
            ->assertRedirect(route('admin.loyalty.index', absolute: false));

        $this->actingAs($admin)
            ->put(route('admin.loyalty.benefits.update', $benefit, absolute: false), [
                'name' => '6% loyalty discount',
                'description' => 'Adjusted benefit copy.',
                'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                'value' => 6,
                'display_order' => 1,
                'is_highlighted' => true,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.loyalty.index', absolute: false));

        $this->assertDatabaseHas('loyalty_tiers', [
            'id' => $tier->id,
            'name' => 'Regular Plus',
            'badge_label' => 'Level 1+',
        ]);
        $this->assertDatabaseHas('loyalty_rules', [
            'id' => $rule->id,
            'name' => 'Regular tier adjusted rule',
            'min_completed_orders' => 4,
        ]);
        $this->assertDatabaseHas('loyalty_benefits', [
            'id' => $benefit->id,
            'name' => '6% loyalty discount',
            'value' => '6.00',
        ]);
    }

    public function test_shared_order_events_are_registered_for_loyalty_recalculation(): void
    {
        Event::fake();

        Event::assertListening(OrderCreated::class, RecalculateUserLoyaltyListener::class);
        Event::assertListening(OrderCompleted::class, RecalculateUserLoyaltyListener::class);
        Event::assertListening(PaymentSucceeded::class, RecalculateUserLoyaltyListener::class);
        Event::assertListening(RefundIssued::class, RecalculateUserLoyaltyListener::class);
    }

    private function createQualifiedOrder(User $customer, float $amount, Carbon $createdAt): Order
    {
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Loyalty Provider',
            'booking_reference' => 'BK-LOYALTY-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['airline' => 'Booke Air'],
            'currency' => 'USD',
            'total_amount' => number_format($amount, 2, '.', ''),
            'request_payload' => ['airline' => 'Booke Air'],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'USD',
            'source' => FinancialTransaction::SOURCE_PAYMENT_STATUS_PAID,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $order->fresh('transactions');
    }
}