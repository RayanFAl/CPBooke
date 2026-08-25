<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\User;
use App\Modules\Content\Support\ContentPageCatalog;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPagesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_product_policy_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.content.store'), [
                'slug' => 'ignored-slug',
                'category' => ContentPageCatalog::CATEGORY_PRODUCT_POLICY,
                'product' => ContentPageCatalog::PRODUCT_FLIGHT,
                'title_en' => 'Flight Policies',
                'title_ar' => 'سياسات الطيران',
                'body_en' => '<p>CPBooke flight policy body</p>',
                'body_ar' => '<p>نص سياسة الطيران</p>',
                'url' => 'https://example.com/flight-policy',
                'sort_order' => 10,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.content.index'));

        $this->assertDatabaseHas('content_pages', [
            'slug' => 'flight-policy',
            'category' => ContentPageCatalog::CATEGORY_PRODUCT_POLICY,
            'product' => ContentPageCatalog::PRODUCT_FLIGHT,
            'title_en' => 'Flight Policies',
            'title_ar' => 'سياسات الطيران',
            'url' => 'https://example.com/flight-policy',
        ]);
    }

    public function test_admin_cannot_create_two_policies_for_the_same_product(): void
    {
        $admin = $this->admin();

        ContentPage::query()->create([
            'slug' => 'flight-policy',
            'category' => ContentPageCatalog::CATEGORY_PRODUCT_POLICY,
            'product' => ContentPageCatalog::PRODUCT_FLIGHT,
            'title_en' => 'Flight Policies',
            'title_ar' => 'سياسات الطيران',
            'body_en' => 'Existing',
            'body_ar' => 'موجود',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.content.create'))
            ->post(route('admin.content.store'), [
                'slug' => 'flight-policy-alt',
                'category' => ContentPageCatalog::CATEGORY_PRODUCT_POLICY,
                'product' => ContentPageCatalog::PRODUCT_FLIGHT,
                'title_en' => 'Another flight policy',
                'title_ar' => 'سياسة أخرى',
                'body_en' => 'Duplicate product',
                'body_ar' => 'منتج مكرر',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.content.create'))
            ->assertSessionHasErrors('product');
    }

    public function test_legal_pages_clear_the_product_field(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.content.store'), [
                'slug' => ContentPageCatalog::SLUG_TERMS_OF_SERVICE,
                'category' => ContentPageCatalog::CATEGORY_LEGAL,
                'product' => ContentPageCatalog::PRODUCT_FLIGHT,
                'title_en' => 'Terms and Conditions',
                'title_ar' => 'الشروط والأحكام',
                'body_en' => '<p>Legal body</p>',
                'body_ar' => '<p>نص قانوني</p>',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.content.index'));

        $this->assertDatabaseHas('content_pages', [
            'slug' => 'terms-of-service',
            'category' => ContentPageCatalog::CATEGORY_LEGAL,
            'product' => null,
            'url' => null,
        ]);
    }

    public function test_admin_rejects_non_https_public_url(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.content.create'))
            ->post(route('admin.content.store'), [
                'slug' => ContentPageCatalog::SLUG_PRIVACY_POLICY,
                'category' => ContentPageCatalog::CATEGORY_LEGAL,
                'title_en' => 'Privacy Policy',
                'title_ar' => 'سياسة الخصوصية',
                'body_en' => '<p>Body</p>',
                'body_ar' => '<p>المحتوى</p>',
                'url' => 'http://example.com/privacy',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.content.create'))
            ->assertSessionHasErrors('url');
    }

    public function test_admin_workspace_index_returns_all_tabs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.content.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/content/pages/Index', false)
                ->has('tabs', 6)
                ->where('tabs.0.tab_id', ContentPageCatalog::SLUG_PRIVACY_POLICY)
                ->where('tabs.2.tab_id', ContentPageCatalog::PRODUCT_FLIGHT)
                ->where('tabs.2.group', 'product')
            );
    }

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $admin->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        return $admin;
    }
}
