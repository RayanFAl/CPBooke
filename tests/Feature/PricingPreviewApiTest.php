<?php

namespace Tests\Feature;

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Models\User;
use App\Models\UserLoyaltyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingPreviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_returns_correct_structure_and_loyalty_adjustment(): void
    {
        $customer = $this->createCustomerWithDiscountBenefit();

        $response = $this->actingAsCustomer($customer)->postJson(route('api.v1.pricing.preview'), [
            'user_id' => $customer->id,
            'service_type' => 'hotel',
            'currency' => 'LYD',
            'base_amount' => 1200,
            'provider_name' => 'default',
            'attributes' => [
                'allow_finance_sensitive' => false,
            ],
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'base_amount' => 1200.0,
                    'discount_total' => 84.0,
                    'final_amount' => 1116.0,
                    'currency' => 'LYD',
                    'pricing_version' => 'pricing:v1|loyalty:7',
                    'adjustments' => [
                        [
                            'source_type' => 'loyalty',
                            'code' => 'gold_discount',
                            'label' => 'Gold Tier Discount',
                            'adjustment_type' => 'percentage',
                            'applied_amount' => 84.0,
                        ],
                    ],
                ],
            ]);
    }

    public function test_preview_uses_authenticated_customer_when_user_id_missing(): void
    {
        $customer = $this->createCustomerWithDiscountBenefit();

        $this->actingAsCustomer($customer)
            ->postJson(route('api.v1.pricing.preview'), [
                'service_type' => 'hotel',
                'currency' => 'LYD',
                'base_amount' => 1200,
            ])
            ->assertOk()
            ->assertJsonPath('data.discount_total', 84)
            ->assertJsonPath('data.final_amount', 1116);
    }

    public function test_preview_does_not_create_orders_or_side_effect_records(): void
    {
        $customer = $this->createCustomerWithDiscountBenefit();

        $before = [
            'orders' => $this->countTable('orders'),
            'financial_transactions' => $this->countTable('financial_transactions'),
            'order_pricing_adjustments' => $this->countTable('order_pricing_adjustments'),
            'loyalty_histories' => $this->countTable('loyalty_histories'),
        ];

        $this->actingAsCustomer($customer)->postJson(route('api.v1.pricing.preview'), [
            'user_id' => $customer->id,
            'service_type' => 'flight',
            'currency' => 'LYD',
            'base_amount' => 500,
            'provider_name' => 'default',
        ])->assertOk();

        $after = [
            'orders' => $this->countTable('orders'),
            'financial_transactions' => $this->countTable('financial_transactions'),
            'order_pricing_adjustments' => $this->countTable('order_pricing_adjustments'),
            'loyalty_histories' => $this->countTable('loyalty_histories'),
        ];

        $this->assertSame($before, $after);
    }

    public function test_preview_is_deterministic_for_same_input(): void
    {
        $customer = $this->createCustomerWithDiscountBenefit();

        $payload = [
            'user_id' => $customer->id,
            'service_type' => 'hotel',
            'currency' => 'LYD',
            'base_amount' => 1200,
            'provider_name' => 'default',
            'attributes' => [
                'allow_finance_sensitive' => false,
            ],
        ];

        $first = $this->actingAsCustomer($customer)
            ->postJson(route('api.v1.pricing.preview'), $payload)
            ->assertOk()
            ->json();

        $second = $this->actingAsCustomer($customer)
            ->postJson(route('api.v1.pricing.preview'), $payload)
            ->assertOk()
            ->json();

        $this->assertSame($first, $second);
    }

    public function test_preview_supports_get_requests(): void
    {
        $customer = $this->createCustomerWithDiscountBenefit();

        $this->actingAsCustomer($customer)
            ->getJson(route('api.v1.pricing.preview', [
                'service_type' => 'flight',
                'base_amount' => 500,
                'currency' => 'LYD',
            ]))
            ->assertOk()
            ->assertJsonPath('data.discount_total', 35)
            ->assertJsonPath('data.final_amount', 465);
    }

    public function test_preview_respects_service_type_filters_for_multiple_services(): void
    {
        $customer = $this->createCustomerWithDiscountBenefit();

        $this->actingAsCustomer($customer)
            ->postJson(route('api.v1.pricing.preview'), [
                'service_type' => 'insurance',
                'currency' => 'LYD',
                'base_amount' => 1200,
            ])
            ->assertOk()
            ->assertJsonPath('data.discount_total', 0)
            ->assertJsonPath('data.final_amount', 1200)
            ->assertJsonCount(0, 'data.adjustments');
    }

    public function test_preview_forbids_requesting_another_user_pricing(): void
    {
        $customer = $this->createCustomerWithDiscountBenefit();
        $otherCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->actingAsCustomer($customer)
            ->postJson(route('api.v1.pricing.preview'), [
                'user_id' => $otherCustomer->id,
                'service_type' => 'hotel',
                'base_amount' => 1200,
            ])
            ->assertForbidden();
    }

    public function test_preview_validates_required_fields(): void
    {
        $customer = $this->createCustomerWithDiscountBenefit();

        $this->actingAsCustomer($customer)
            ->postJson(route('api.v1.pricing.preview'), [
                'service_type' => 'invalid-service',
                'base_amount' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'errors' => [
                    'service_type',
                    'base_amount',
                ],
            ]);
    }

    private function actingAsCustomer(User $customer): self
    {
        Sanctum::actingAs($customer);

        return $this;
    }

    private function createCustomerWithDiscountBenefit(): User
    {
        LoyaltySetting::query()->create([
            'settings_version' => 7,
            'loyalty_enabled' => true,
            'allow_discount_stacking' => true,
            'default_currency' => 'LYD',
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $tier = LoyaltyTier::query()->create([
            'level' => 9,
            'code' => 'gold',
            'name' => 'Gold',
            'sort_order' => 9,
            'is_active' => true,
            'is_default' => false,
        ]);

        LoyaltyBenefit::query()->create([
            'tier_id' => $tier->id,
            'code' => 'gold_discount',
            'name' => 'Gold Tier Discount',
            'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
            'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
            'value' => 7,
            'applies_to_services' => ['hotel', 'flight'],
            'minimum_order_amount' => 100,
            'priority' => 100,
            'stackable' => true,
            'finance_sensitive' => false,
            'display_order' => 1,
            'is_active' => true,
        ]);

        UserLoyaltyProfile::query()->create([
            'user_id' => $customer->id,
            'current_tier_id' => $tier->id,
            'completed_orders_count' => 5,
            'lifetime_spend' => 5000,
            'period_spend' => 1200,
            'progress_percentage' => 100,
        ]);

        return $customer;
    }

    private function countTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) app('db')->table($table)->count();
    }
}