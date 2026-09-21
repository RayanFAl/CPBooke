<?php

namespace Tests\Feature;

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyCompanyRate;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Models\User;
use App\Models\UserLoyaltyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyMobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_load_loyalty_rates_and_resolve_airline_discount(): void
    {
        Http::fake([
            '*/v1/airlines' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'airline_code' => 'NB',
                        'airline_name' => 'Berniq Air',
                        'logo_url' => 'https://agency.atom.ly/agency/median/api/v1/airlines/NB/logo',
                    ],
                ],
            ], 200),
        ]);

        LoyaltySetting::query()->create([
            'loyalty_enabled' => true,
            'visible_in_mobile_app' => true,
            'default_currency' => 'LYD',
            'settings_version' => 1,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $tier = LoyaltyTier::query()->where('level', 1)->firstOrFail();

        LoyaltyBenefit::query()->updateOrCreate(
            ['tier_id' => $tier->id, 'code' => 'level_1_discount'],
            [
                'name' => 'Level 1 discount',
                'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                'value' => 3,
                'applies_to_services' => ['flight', 'hotel', 'insurance', 'esim'],
                'is_active' => true,
                'priority' => 10,
                'display_order' => 1,
            ],
        );

        LoyaltyCompanyRate::query()->updateOrCreate(
            [
                'tier_id' => $tier->id,
                'service_type' => 'flight',
                'company_key' => 'NB',
            ],
            [
                'company_name' => 'Berniq Air',
                'discount_percentage' => 5,
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        LoyaltyCompanyRate::query()->updateOrCreate(
            [
                'tier_id' => $tier->id,
                'service_type' => 'hotel',
                'company_key' => 'hotel',
            ],
            [
                'company_name' => 'Hotels',
                'discount_percentage' => 2,
                'is_active' => true,
                'sort_order' => 1000,
            ],
        );

        UserLoyaltyProfile::query()->create([
            'user_id' => $customer->id,
            'current_tier_id' => $tier->id,
            'completed_orders_count' => 1,
            'lifetime_spend' => 100,
            'period_spend' => 100,
            'progress_percentage' => 10,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.loyalty.rates'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_tier.level', 1)
            ->assertJsonPath('data.services.flight.companies.0.code', 'NB')
            ->assertJsonPath('data.services.flight.companies.0.discount_percentage', 5)
            ->assertJsonPath('data.services.hotel.companies.0.discount_percentage', 2);

        $this->getJson(route('api.v1.loyalty.rates.resolve', [
            'service_type' => 'flight',
            'airline_code' => 'NB',
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.applies', true)
            ->assertJsonPath('data.company_key', 'NB')
            ->assertJsonPath('data.discount_percentage', 5);

        $this->getJson(route('api.v1.loyalty.rates.resolve', [
            'service_type' => 'hotel',
        ]))
            ->assertOk()
            ->assertJsonPath('data.applies', true)
            ->assertJsonPath('data.discount_percentage', 2);
    }

    public function test_customer_can_load_booke_plus_loyalty_profile_catalog(): void
    {
        LoyaltySetting::query()->create([
            'loyalty_enabled' => true,
            'visible_in_mobile_app' => true,
            'default_currency' => 'LYD',
            'settings_version' => 1,
        ]);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $tier = LoyaltyTier::query()->where('level', 1)->firstOrFail();

        LoyaltyBenefit::query()->updateOrCreate(
            ['tier_id' => $tier->id, 'code' => 'level_1_discount'],
            [
                'name' => 'Level 1 discount',
                'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                'value' => 3,
                'applies_to_services' => ['flight', 'hotel', 'insurance', 'esim'],
                'is_active' => true,
                'priority' => 10,
                'display_order' => 1,
                'metadata' => [
                    'label_en' => '3% off bookings',
                    'label_ar' => 'خصم 3% على الحجوزات',
                ],
            ],
        );

        $nextTier = LoyaltyTier::query()->where('code', 'level_2')->firstOrFail();

        UserLoyaltyProfile::query()->create([
            'user_id' => $customer->id,
            'current_tier_id' => $tier->id,
            'next_tier_id' => $nextTier->id,
            'completed_orders_count' => 0,
            'lifetime_spend' => 0,
            'period_spend' => 250.5,
            'progress_percentage' => 5,
            'metadata' => [
                'entitlements' => [
                    (string) $tier->id => [
                        'tier_id' => $tier->id,
                        'tier_code' => $tier->code,
                        'grant_reason' => 'welcome',
                        'ends_after_first_order' => true,
                        'expires_at' => null,
                    ],
                ],
            ],
        ]);

        Sanctum::actingAs($customer);

        $response = $this->getJson(route('api.v1.loyalty.show'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.program.enabled', true)
            ->assertJsonPath('data.program.visible_in_mobile_app', true)
            ->assertJsonPath('data.program.default_currency', 'LYD')
            ->assertJsonPath('data.program.welcome_ends_after_first_order', true)
            ->assertJsonPath('data.program.rules.welcome_ends_after_first_completed_order', true)
            ->assertJsonPath('data.program.results_promo.enabled', true)
            ->assertJsonPath('data.program.results_promo.action_type', 'route')
            ->assertJsonPath('data.program.results_promo.action_value', '/loyalty')
            ->assertJsonPath('data.current_tier.code', 'welcome')
            ->assertJsonPath('data.current_tier.ends_after_first_order', true)
            ->assertJsonPath('data.membership.ends_after_first_order', true)
            ->assertJsonPath('data.current_tier.name_en', 'Welcome')
            ->assertJsonPath('data.current_tier.name_ar', 'مرحباً')
            ->assertJsonPath('data.current_tier.discount_percentage', 3)
            ->assertJsonPath('data.current_tier.monthly_spend_required', 0)
            ->assertJsonPath('data.next_tier.code', 'explorer')
            ->assertJsonPath('data.monthly_spend', 250.5)
            ->assertJsonPath('data.progress_to_next_level.current_metrics.month_spend', 250.5)
            ->assertJsonPath('data.membership.discount_percentage', 3)
            ->assertJsonPath('data.tiers.0.code', 'welcome')
            ->assertJsonPath('data.tiers.1.code', 'explorer')
            ->assertJsonPath('data.tiers.2.code', 'gold')
            ->assertJsonPath('data.tiers.3.code', 'platinum');

        $tiers = $response->json('data.tiers');
        $this->assertIsArray($tiers);
        $this->assertNotEmpty($tiers[0]['benefits'] ?? []);
        $this->assertArrayHasKey('label_ar', $tiers[0]['benefits'][0]);
        $this->assertArrayHasKey('label_en', $tiers[0]['benefits'][0]);

        foreach ($tiers as $entry) {
            $this->assertArrayHasKey('name_ar', $entry);
            $this->assertArrayHasKey('name_en', $entry);
            $this->assertArrayHasKey('monthly_spend_required', $entry);
            $this->assertArrayHasKey('discount_percentage', $entry);
            $this->assertArrayHasKey('benefits', $entry);
            $this->assertIsArray($entry['benefits']);
        }
    }
}
