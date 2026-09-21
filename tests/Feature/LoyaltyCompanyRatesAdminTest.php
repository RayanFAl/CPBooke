<?php

namespace Tests\Feature;

use App\Models\LoyaltyCompanyRate;
use App\Models\LoyaltyTier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyCompanyRatesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_sync_company_rates(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        $this->actingAs($admin)
            ->getJson(route('admin.loyalty.company-rates.show'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $tier = LoyaltyTier::query()->where('level', 1)->firstOrFail();

        $this->actingAs($admin)
            ->putJson(route('admin.loyalty.company-rates.sync'), [
                'rates' => [
                    [
                        'tier_id' => $tier->id,
                        'service_type' => 'flight',
                        'company_key' => '8U',
                        'company_name' => 'Afriqiyah Airways',
                        'discount_percentage' => 4.5,
                        'is_active' => true,
                    ],
                    [
                        'tier_id' => $tier->id,
                        'service_type' => 'hotel',
                        'company_key' => 'hotel',
                        'company_name' => 'Hotels',
                        'discount_percentage' => 2,
                        'is_active' => true,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('loyalty_company_rates', [
            'tier_id' => $tier->id,
            'service_type' => 'flight',
            'company_key' => '8U',
            'discount_percentage' => '4.50',
        ]);

        $this->assertDatabaseHas('loyalty_company_rates', [
            'tier_id' => $tier->id,
            'service_type' => 'hotel',
            'company_key' => 'hotel',
            'discount_percentage' => '2.00',
        ]);

        $this->assertTrue(
            LoyaltyCompanyRate::query()
                ->where('service_type', 'flight')
                ->where('company_key', '8U')
                ->exists(),
        );
    }
}
