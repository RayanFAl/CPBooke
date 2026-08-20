<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Modules\Content\Support\ContentPageCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPagesWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_screen_includes_product_policies_and_excludes_terms(): void
    {
        $this->get('/pages?locale=ar')
            ->assertOk()
            ->assertSee('Booke', false)
            ->assertSee('سياسة الخصوصية', false)
            ->assertSee('سياسة حجز الطيران', false)
            ->assertSee('سياسة حجز الفنادق', false)
            ->assertSee('سياسة التأمين', false)
            ->assertSee('سياسة eSIM', false)
            ->assertDontSee('الشروط والأحكام', false)
            ->assertDontSee('العربية', false)
            ->assertDontSee('>English<', false);

        $this->get('/pages/privacy-policy?locale=ar')
            ->assertOk()
            ->assertSee('سياسة الخصوصية', false)
            ->assertSee('سياسة حجز الطيران', false)
            ->assertDontSee('الشروط والأحكام', false);
    }

    public function test_privacy_policy_web_page_is_public(): void
    {
        $this->createLegalPage();

        $this->get('/pages/privacy-policy?locale=ar')
            ->assertOk()
            ->assertSee('سياسة الخصوصية', false)
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertDontSee('login', false);
    }

    public function test_terms_are_on_a_separate_web_screen(): void
    {
        ContentPage::query()->create([
            'slug' => ContentPageCatalog::SLUG_TERMS_OF_SERVICE,
            'category' => ContentPageCatalog::CATEGORY_LEGAL,
            'title_en' => 'Terms and Conditions',
            'title_ar' => 'الشروط والأحكام',
            'body_en' => '<p>EN terms body</p>',
            'body_ar' => '<p>نص الشروط</p>',
            'is_active' => true,
        ]);

        $this->get('/pages/terms-of-service?locale=en')
            ->assertOk()
            ->assertSee('Booke', false)
            ->assertSee('EN terms body', false)
            ->assertDontSee('Flight booking policy', false)
            ->assertDontSee('Privacy Policy', false);
    }

    public function test_product_policy_web_alias_opens_privacy_screen(): void
    {
        ContentPage::query()->create([
            'slug' => 'flight-policy',
            'category' => ContentPageCatalog::CATEGORY_PRODUCT_POLICY,
            'product' => ContentPageCatalog::PRODUCT_FLIGHT,
            'title_en' => 'Flight Policies',
            'title_ar' => 'سياسات الطيران',
            'body_en' => '<p>Flight body EN</p>',
            'body_ar' => '<p>نص سياسة الطيران</p>',
            'is_active' => true,
        ]);

        $this->get('/pages/product/flight?locale=en')
            ->assertOk()
            ->assertSee('Flight body EN', false)
            ->assertDontSee('Terms and Conditions', false);

        $this->get('/pages/flight-policy?locale=en')
            ->assertOk()
            ->assertSee('Flight body EN', false);
    }

    public function test_privacy_shortcut_redirects_to_public_page(): void
    {
        $this->createLegalPage();

        $this->get('/privacy-policy')
            ->assertRedirect('/pages/privacy-policy');
    }

    public function test_inactive_web_page_returns_404(): void
    {
        ContentPage::query()->create([
            'slug' => ContentPageCatalog::SLUG_PRIVACY_POLICY,
            'title_en' => 'Privacy Policy',
            'title_ar' => 'سياسة الخصوصية',
            'body_en' => '<p>Hidden</p>',
            'body_ar' => '<p>مخفي</p>',
            'is_active' => false,
        ]);

        $this->get('/pages/privacy-policy')->assertNotFound();
    }

    private function createLegalPage(): ContentPage
    {
        return ContentPage::query()->create([
            'slug' => ContentPageCatalog::SLUG_PRIVACY_POLICY,
            'category' => ContentPageCatalog::CATEGORY_LEGAL,
            'title_en' => 'Privacy Policy',
            'title_ar' => 'سياسة الخصوصية',
            'body_en' => '<p>EN privacy body</p>',
            'body_ar' => '<h1>سياسة الخصوصية</h1><p>AR privacy body</p>',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
