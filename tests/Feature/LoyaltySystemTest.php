<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyHistory;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTier;
use App\Models\Order;
use App\Models\User;
use App\Modules\Loyalty\Listeners\InitializeUserLoyaltyOnRegistrationListener;
use App\Modules\Loyalty\Listeners\RecalculateUserLoyaltyListener;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\PaymentSucceeded;
use App\Modules\Orders\Events\RefundIssued;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltySystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_loyalty_unlocks_level_one_discount_when_monthly_spend_target_is_met(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->createQualifiedOrder($customer, 600.00, Carbon::parse('2026-06-10 10:00:00'));
        $this->createQualifiedOrder($customer, 500.00, Carbon::parse('2026-06-12 10:00:00'));

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

        $this->assertSame('level_1', $profile->currentTier?->code);
        $this->assertSame('level_2', $profile->nextTier?->code);
        $this->assertSame('1100.00', number_format((float) $profile->period_spend, 2, '.', ''));
        $this->assertSame('25.00', $application['pricing']['discount_amount']);
        $this->assertSame('475.00', $application['pricing']['final_total']);
        $this->assertNotEmpty($profile->metadata['entitlements'] ?? []);
        $this->assertDatabaseHas('loyalty_history', [
            'user_id' => $customer->id,
            'action' => LoyaltyHistory::ACTION_UPGRADED,
            'to_tier_id' => $profile->current_tier_id,
        ]);

        Carbon::setTestNow();
    }

    public function test_loyalty_discount_applies_to_fare_only_and_preserves_tax(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->createQualifiedOrder($customer, 600.00, Carbon::parse('2026-06-10 10:00:00'));
        $this->createQualifiedOrder($customer, 500.00, Carbon::parse('2026-06-12 10:00:00'));

        $service = app(LoyaltyService::class);
        $service->upgradeUserIfEligible($customer);

        $orderPreview = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Loyalty Preview Provider',
            'booking_reference' => 'BK-LOYALTY-TAX',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['airline' => 'Buraq Air'],
            'currency' => 'LYD',
            'total_amount' => '740.00',
            'base_amount' => '613.00',
            'tax_amount' => '127.00',
            'request_payload' => ['airline' => 'Buraq Air'],
        ]);

        $application = $service->applyBenefitsToOrder($orderPreview->load('customer'));

        $this->assertSame('740.00', $application['pricing']['base_total']);
        $this->assertSame('613.00', $application['pricing']['fare_amount']);
        $this->assertSame('127.00', $application['pricing']['tax_amount']);
        $this->assertSame('30.65', $application['pricing']['discount_amount']);
        $this->assertSame('709.35', $application['pricing']['final_total']);

        Carbon::setTestNow();
    }

    public function test_loyalty_uses_highest_active_level_when_multiple_targets_are_met(): void
    {
        Carbon::setTestNow('2026-06-20 12:00:00');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->createQualifiedOrder($customer, 15000.00, Carbon::parse('2026-06-05 10:00:00'));
        $this->createQualifiedOrder($customer, 12000.00, Carbon::parse('2026-06-18 10:00:00'));

        $profile = app(LoyaltyService::class)->upgradeUserIfEligible($customer);

        $this->assertSame('vip', $profile->currentTier?->code);
        $this->assertNull($profile->nextTier);

        Carbon::setTestNow();
    }

    public function test_profile_api_exposes_monthly_spend_progress_and_entitlement(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->createQualifiedOrder($customer, 1000.00, Carbon::parse('2026-06-08 10:00:00'));

        app(LoyaltyService::class)->upgradeUserIfEligible($customer);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/users/profile')
            ->assertOk()
            ->assertJsonPath('data.user.loyalty.current_tier.code', 'level_1')
            ->assertJsonPath('data.user.loyalty.current_level', 1)
            ->assertJsonPath('data.user.loyalty.next_tier.code', 'level_2')
            ->assertJsonPath('data.user.loyalty.progress_to_next_level.current_metrics.month_spend', '1000.00')
            ->assertJsonPath('data.user.loyalty.progress_to_next_level.next_threshold', '5000.00')
            ->assertJsonPath('data.user.loyalty.benefits_unlocked.0.code', 'level_1_discount')
            ->assertJsonPath('data.user.loyalty.membership.discount_percentage', 5)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'loyalty' => [
                            'entitlement' => [
                                'expires_at',
                                'days_remaining',
                                'duration_months',
                            ],
                        ],
                    ],
                ],
            ]);

        Carbon::setTestNow();
    }

    public function test_admin_can_view_loyalty_dashboard_and_update_tiers_rules_and_benefits(): void
    {
        Carbon::setTestNow('2026-06-12 12:00:00');

        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->createQualifiedOrder($customer, 1200.00, Carbon::parse('2026-06-05 10:00:00'));

        app(LoyaltyService::class)->upgradeUserIfEligible($customer);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        $tier = LoyaltyTier::query()->where('code', 'level_1')->firstOrFail();
        $rule = LoyaltyRule::query()->where('tier_id', $tier->id)->firstOrFail();
        $benefit = LoyaltyBenefit::query()->where('tier_id', $tier->id)->where('code', 'level_1_discount')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.loyalty.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/loyalty/pages/Index', false)
                ->where('program.loyalty_enabled', true)
                ->where('dashboard.metrics.profiles', 1)
                ->where('dashboard.tiers.1.code', 'level_1')
                ->where('dashboard.users_per_tier.1.users.0.user.id', $customer->id)
            );

        $this->actingAs($admin)
            ->put(route('admin.loyalty.tiers.update', $tier, absolute: false), [
                'code' => 'level_1',
                'name' => 'Level 1 Plus',
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
                'name' => 'Level 1 adjusted monthly target',
                'rule_type' => LoyaltyRule::TYPE_UPGRADE,
                'min_completed_orders' => 0,
                'min_lifetime_spend' => 0,
                'min_period_orders' => 0,
                'min_period_spend' => 900,
                'period_days' => 30,
                'allow_downgrade' => true,
                'is_active' => true,
                'priority' => 1,
            ])
            ->assertRedirect(route('admin.loyalty.index', absolute: false));

        $this->actingAs($admin)
            ->put(route('admin.loyalty.benefits.update', $benefit, absolute: false), [
                'name' => '12% loyalty discount',
                'description' => 'Adjusted benefit copy.',
                'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                'value' => 12,
                'display_order' => 1,
                'is_highlighted' => true,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.loyalty.index', absolute: false));

        $this->assertDatabaseHas('loyalty_tiers', [
            'id' => $tier->id,
            'name' => 'Level 1 Plus',
            'badge_label' => 'Level 1+',
        ]);
        $this->assertDatabaseHas('loyalty_rules', [
            'id' => $rule->id,
            'name' => 'Level 1 adjusted monthly target',
            'min_period_spend' => 900,
        ]);
        $this->assertDatabaseHas('loyalty_benefits', [
            'id' => $benefit->id,
            'name' => '12% loyalty discount',
            'value' => '12.00',
        ]);

        Carbon::setTestNow();
    }

    public function test_registration_creates_profile_without_active_discount_until_spend_target_is_met(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        event(new Registered($customer));

        $profile = $customer->fresh()->loyaltyProfile()->first();

        $this->assertNotNull($profile);
        $this->assertNull($profile->current_tier_id);

        $payload = app(LoyaltyService::class)->profilePayload($customer, initializeIfMissing: false);

        $this->assertFalse($payload['show_welcome_message']);
        $this->assertNull($payload['current_tier']);
        $this->assertNull($payload['membership']);
    }

    public function test_registered_event_is_listened_for_loyalty_initialization(): void
    {
        Event::fake();

        Event::assertListening(Registered::class, InitializeUserLoyaltyOnRegistrationListener::class);
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
