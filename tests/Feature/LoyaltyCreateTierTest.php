<?php

namespace Tests\Feature;

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyCompanyRate;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTier;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyCreateTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_new_loyalty_level(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        $maxLevelBefore = (int) LoyaltyTier::query()->max('level');

        $this->actingAs($admin)
            ->post(route('admin.loyalty.tiers.store'), [
                'name' => 'Level Elite',
                'discount_percentage' => 12.5,
                'monthly_spend' => 30000,
                'duration_months' => 12,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.loyalty.index'));

        $tier = LoyaltyTier::query()->where('name', 'Level Elite')->first();

        $this->assertNotNull($tier);
        $this->assertSame($maxLevelBefore + 1, (int) $tier->level);
        $this->assertTrue((bool) $tier->is_active);

        $this->assertDatabaseHas('loyalty_rules', [
            'tier_id' => $tier->id,
            'rule_type' => LoyaltyRule::TYPE_UPGRADE,
            'min_period_spend' => '30000.00',
        ]);

        $rule = LoyaltyRule::query()->where('tier_id', $tier->id)->first();
        $this->assertSame(12, $rule?->metadata['benefit_duration_months'] ?? null);
        $this->assertSame('months', $rule?->metadata['benefit_duration_unit'] ?? null);

        $this->assertDatabaseHas('loyalty_benefits', [
            'tier_id' => $tier->id,
            'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
            'value' => '12.50',
        ]);

        $this->assertTrue(
            LoyaltyCompanyRate::query()
                ->where('tier_id', $tier->id)
                ->where('service_type', 'hotel')
                ->where('company_key', 'hotel')
                ->exists(),
        );
    }

    public function test_admin_can_create_loyalty_level_with_short_day_duration(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        $this->actingAs($admin)
            ->post(route('admin.loyalty.tiers.store'), [
                'name' => 'Flash 3 Days',
                'discount_percentage' => 5,
                'monthly_spend' => 1000,
                'duration_unit' => 'days',
                'duration_days' => 3,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.loyalty.index'));

        $tier = LoyaltyTier::query()->where('name', 'Flash 3 Days')->first();
        $this->assertNotNull($tier);

        $rule = LoyaltyRule::query()->where('tier_id', $tier->id)->first();
        $this->assertSame('days', $rule?->metadata['benefit_duration_unit'] ?? null);
        $this->assertSame(3, $rule?->metadata['benefit_duration_days'] ?? null);
        $this->assertNull($rule?->metadata['benefit_duration_months'] ?? null);
    }

    public function test_creating_short_day_campaign_notifies_all_customers(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        $this->actingAs($admin)
            ->post(route('admin.loyalty.tiers.store'), [
                'name' => 'Eid Flash',
                'discount_percentage' => 7,
                'monthly_spend' => 0,
                'duration_unit' => 'days',
                'duration_days' => 3,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.loyalty.index'));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $customer->id,
            'template_code' => 'LOYALTY_DISCOUNT_CAMPAIGN',
        ]);

        $this->assertSame(
            1,
            UserNotification::query()
                ->where('user_id', $customer->id)
                ->where('template_code', 'LOYALTY_DISCOUNT_CAMPAIGN')
                ->count(),
        );
    }

    public function test_creating_level_without_notify_flag_skips_campaign_push(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        $this->actingAs($admin)
            ->post(route('admin.loyalty.tiers.store'), [
                'name' => 'Quiet Level',
                'discount_percentage' => 4,
                'monthly_spend' => 5000,
                'duration_unit' => 'months',
                'duration_months' => 6,
                'is_active' => true,
                'notify_customers' => false,
            ])
            ->assertRedirect(route('admin.loyalty.index'));

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $customer->id,
            'template_code' => 'LOYALTY_DISCOUNT_CAMPAIGN',
        ]);
    }

    public function test_admin_can_duplicate_an_existing_loyalty_level(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        $source = LoyaltyTier::query()->where('code', 'level_2')->firstOrFail();
        $sourceRule = LoyaltyRule::query()->where('tier_id', $source->id)->firstOrFail();
        $sourceBenefit = LoyaltyBenefit::query()
            ->where('tier_id', $source->id)
            ->where('benefit_type', LoyaltyBenefit::TYPE_DISCOUNT)
            ->firstOrFail();

        LoyaltyCompanyRate::query()->updateOrCreate(
            [
                'tier_id' => $source->id,
                'service_type' => 'hotel',
                'company_key' => 'hotel',
            ],
            [
                'company_name' => 'Hotels',
                'discount_percentage' => 8,
                'is_active' => true,
                'sort_order' => 1000,
            ],
        );

        $maxLevelBefore = (int) LoyaltyTier::query()->max('level');

        $this->actingAs($admin)
            ->post(route('admin.loyalty.tiers.duplicate', $source))
            ->assertRedirect(route('admin.loyalty.index'));

        $copy = LoyaltyTier::query()->where('name', $source->name.' (copy)')->first();
        $this->assertNotNull($copy);
        $this->assertSame($maxLevelBefore + 1, (int) $copy->level);
        $this->assertFalse((bool) $copy->is_default);
        $this->assertSame($source->id, $copy->metadata['duplicated_from_tier_id'] ?? null);

        $this->assertDatabaseHas('loyalty_rules', [
            'tier_id' => $copy->id,
            'min_period_spend' => $sourceRule->min_period_spend,
        ]);

        $this->assertDatabaseHas('loyalty_benefits', [
            'tier_id' => $copy->id,
            'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
            'value' => $sourceBenefit->value,
        ]);

        $this->assertTrue(
            LoyaltyCompanyRate::query()
                ->where('tier_id', $copy->id)
                ->where('service_type', 'hotel')
                ->where('company_key', 'hotel')
                ->where('discount_percentage', '8.00')
                ->exists(),
        );
    }
}
