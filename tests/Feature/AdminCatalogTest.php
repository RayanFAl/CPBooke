<?php

namespace Tests\Feature;

use App\Models\MobileCatalogType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_catalog_index(): void
    {
        MobileCatalogType::query()->create([
            'public_id' => 'cat_travel',
            'key' => 'travel_insurance',
            'title_en' => 'Travel insurance',
            'title_ar' => 'تأمين سفر',
            'action_type' => 'route',
            'action_value' => '/insurance/travel',
            'sort_order' => 1,
            'is_active' => true,
            'show_in_options' => true,
            'show_in_market' => true,
        ]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.catalog.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/catalog/pages/Index', false)
                ->has('types', 1)
                ->where('types.0.key', 'travel_insurance')
            );
    }

    public function test_admin_can_create_a_catalog_type(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.catalog.store'), [
                'title_en' => 'Travel insurance',
                'title_ar' => 'تأمين سفر',
                'key' => 'travel_insurance',
                'options_image_url' => 'https://cdn.example.com/options/travel.jpg',
                'market_image_url' => 'https://cdn.example.com/market/travel.jpg',
                'action_type' => 'route',
                'action_value' => '/insurance/travel',
                'sort_order' => 1,
                'is_active' => 1,
                'show_in_options' => 1,
                'show_in_market' => 1,
            ])
            ->assertRedirect(route('admin.catalog.index'));

        $this->assertDatabaseHas('mobile_catalog_types', [
            'key' => 'travel_insurance',
            'title_en' => 'Travel insurance',
            'title_ar' => 'تأمين سفر',
            'action_value' => '/insurance/travel',
        ]);
    }

    public function test_admin_can_add_a_custom_type_and_generate_key(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.catalog.store'), [
                'title_en' => 'Visa support',
                'title_ar' => 'دعم التأشيرة',
                'action_type' => 'route',
                'action_value' => '/visa',
                'is_active' => 1,
                'show_in_options' => 1,
                'show_in_market' => 0,
            ])
            ->assertRedirect(route('admin.catalog.index'));

        $this->assertDatabaseHas('mobile_catalog_types', [
            'key' => 'visa-support',
            'title_en' => 'Visa support',
            'show_in_market' => 0,
        ]);
    }

    public function test_admin_can_update_and_delete_a_catalog_type(): void
    {
        $type = MobileCatalogType::query()->create([
            'public_id' => 'cat_esim',
            'key' => 'esim',
            'title_en' => 'eSIM',
            'action_type' => 'search_esim',
            'action_value' => '/esim',
            'is_active' => true,
            'show_in_options' => true,
            'show_in_market' => true,
        ]);

        $this->actingAs($this->adminUser())
            ->post(route('admin.catalog.update', $type), [
                'title_en' => 'eSIM packs',
                'title_ar' => 'باقات eSIM',
                'key' => 'esim',
                'action_type' => 'search_esim',
                'action_value' => '/esim',
                'is_active' => 1,
                'show_in_options' => 1,
                'show_in_market' => 1,
            ])
            ->assertRedirect(route('admin.catalog.index'));

        $this->assertDatabaseHas('mobile_catalog_types', [
            'id' => $type->id,
            'title_en' => 'eSIM packs',
        ]);

        $this->actingAs($this->adminUser())
            ->delete(route('admin.catalog.destroy', $type))
            ->assertRedirect(route('admin.catalog.index'));

        $this->assertDatabaseMissing('mobile_catalog_types', [
            'id' => $type->id,
        ]);
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh()->syncRolesByName(['super_admin']);

        return $admin;
    }
}
