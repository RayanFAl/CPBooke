<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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

    public function test_loyalty_unlocks_level_one_discount_on_registration_without_spend(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

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
        $this->assertSame('15.00', $application['pricing']['discount_amount']);
        $this->assertSame('485.00', $application['pricing']['final_total']);
        $this->assertNotEmpty($profile->metadata['entitlements'] ?? []);
        $this->assertNull($profile->metadata['entitlements'][(string) $profile->current_tier_id]['expires_at']);
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
        $this->assertSame('18.39', $application['pricing']['discount_amount']);
        $this->assertSame('721.61', $application['pricing']['final_total']);

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
            ->assertJsonPath('data.user.loyalty.program.enabled', true)
            ->assertJsonPath('data.user.loyalty.current_tier.code', 'level_1')
            ->assertJsonPath('data.user.loyalty.current_tier.discount_percentage', 3)
            ->assertJsonPath('data.user.loyalty.current_level', 1)
            ->assertJsonPath('data.user.loyalty.next_tier.code', 'level_2')
            ->assertJsonPath('data.user.loyalty.next_tier.discount_percentage', 8)
            ->assertJsonPath('data.user.loyalty.progress_to_next_level.current_metrics.month_spend', '1000.00')
            ->assertJsonPath('data.user.loyalty.progress_to_next_level.next_threshold', '5000.00')
            ->assertJsonPath('data.user.loyalty.progress_to_next_level.amount_remaining', '4000.00')
            ->assertJsonPath('data.user.loyalty.benefits_unlocked.0.code', 'level_1_discount')
            ->assertJsonPath('data.user.loyalty.membership.discount_percentage', 3)
            ->assertJsonPath('data.user.loyalty.membership.expires_at', null)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'loyalty' => [
                            'tiers' => [
                                [
                                    'code',
                                    'name',
                                    'discount_percentage',
                                    'monthly_spend_required',
                                    'active_for_months',
                                ],
                            ],
                            'entitlement' => [
                                'expires_at',
                                'days_remaining',
                                'duration_months',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.loyalty.tiers.0.code', 'level_1')
            ->assertJsonPath('data.user.loyalty.next_tier.discount_percentage', 8);

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
                'benefit_duration_months' => 6,
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
        $this->assertSame(6, $rule->fresh()->metadata['benefit_duration_months'] ?? null);
        $this->assertDatabaseHas('loyalty_benefits', [
            'id' => $benefit->id,
            'name' => '12% loyalty discount',
            'value' => '12.00',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => AuditLog::MODULE_LOYALTY,
            'action' => 'loyalty.tier.updated',
            'entity_type' => AuditLog::ENTITY_LOYALTY_TIER,
            'entity_id' => $tier->id,
            'status' => AuditLog::STATUS_SUCCESS,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => AuditLog::MODULE_LOYALTY,
            'action' => 'loyalty.rule.updated',
            'entity_type' => AuditLog::ENTITY_LOYALTY_RULE,
            'entity_id' => $rule->id,
            'status' => AuditLog::STATUS_SUCCESS,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => AuditLog::MODULE_LOYALTY,
            'action' => 'loyalty.benefit.updated',
            'entity_type' => AuditLog::ENTITY_LOYALTY_BENEFIT,
            'entity_id' => $benefit->id,
            'status' => AuditLog::STATUS_SUCCESS,
        ]);

        $tierAudit = AuditLog::query()
            ->where('action', 'loyalty.tier.updated')
            ->where('entity_id', $tier->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($tierAudit);
        $this->assertSame('Level 1 Plus', $tierAudit->new_values['name'] ?? null);
        $this->assertArrayHasKey('name', $tierAudit->old_values ?? []);

        Carbon::setTestNow();
    }

    public function test_registration_assigns_permanent_level_one_discount(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        event(new Registered($customer));
        $this->app->terminate();

        $profile = $customer->fresh()->loyaltyProfile()->first();

        $this->assertNotNull($profile);
        $this->assertNotNull($profile->current_tier_id);
        $this->assertSame('level_1', $profile->currentTier?->code);

        $payload = app(LoyaltyService::class)->profilePayload($customer, initializeIfMissing: false);

        $this->assertFalse($payload['show_welcome_message']);
        $this->assertSame('level_1', $payload['current_tier']['code'] ?? null);
        $this->assertSame(3.0, $payload['membership']['discount_percentage'] ?? null);
        $this->assertArrayHasKey('expires_at', $payload['membership'] ?? []);
        $this->assertNull($payload['membership']['expires_at']);
        $this->assertNotEmpty($payload['tiers']);
        $this->assertSame('level_2', $payload['next_tier']['code'] ?? null);
        $this->assertSame('5000.00', $payload['progress_to_next_level']['amount_remaining']);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $customer->id,
            'template_code' => 'ACCOUNT_WELCOME_LOYALTY',
        ]);
    }

    public function test_api_register_sends_welcome_loyalty_push_without_queue(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Welcome Customer',
            'email' => 'welcome-loyalty@example.com',
            'phone' => '+218912345678',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'device_name' => 'android-test',
            'remember_me' => false,
        ]);

        $response->assertCreated();

        $userId = (int) $response->json('data.user.id');
        $this->assertGreaterThan(0, $userId);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $userId,
            'template_code' => 'ACCOUNT_WELCOME_LOYALTY',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $userId,
            'template_code' => 'ACCOUNT_WELCOME_LOYALTY',
            'channel' => 'push',
        ]);

        $this->assertDatabaseMissing('jobs', [
            'queue' => 'notifications-dispatch',
        ]);
    }

    public function test_login_assigns_permanent_level_one_discount_for_existing_customers(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'email' => 'loyalty-login@example.com',
            'password' => 'password',
        ]);

        $this->assertNull($customer->loyaltyProfile()->first());

        $this->postJson('/api/v1/auth/login', [
            'login' => 'loyalty-login@example.com',
            'password' => 'password',
            'device_name' => 'loyalty-test',
            'remember_me' => false,
        ])->assertOk();

        $profile = $customer->fresh()->loyaltyProfile()->with('currentTier')->first();

        $this->assertNotNull($profile);
        $this->assertSame('level_1', $profile->currentTier?->code);
        $this->assertSame(3.0, app(LoyaltyService::class)->profilePayload($customer, false)['membership']['discount_percentage'] ?? null);
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
