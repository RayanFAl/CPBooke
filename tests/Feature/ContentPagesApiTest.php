<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Modules\Content\Support\ContentPageCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPagesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_index_is_public_and_returns_active_pages_only(): void
    {
        ContentPage::query()->create([
            'slug' => ContentPageCatalog::SLUG_PRIVACY_POLICY,
            'title_en' => 'Privacy Policy',
            'title_ar' => 'سياسة الخصوصية',
            'body_en' => '<p>EN privacy body</p>',
            'body_ar' => '<p>AR privacy body</p>',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        ContentPage::query()->create([
            'slug' => 'draft-page',
            'title_en' => 'Draft',
            'title_ar' => 'مسودة',
            'body_en' => 'Hidden',
            'body_ar' => 'مخفي',
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/pages?locale=en')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.slug', 'privacy-policy')
            ->assertJsonPath('data.0.title', 'Privacy Policy')
            ->assertJsonPath('data.0.body', '<p>EN privacy body</p>')
            ->assertJsonPath('data.0.category', ContentPageCatalog::CATEGORY_LEGAL)
            ->assertJsonPath('data.0.product', null)
            ->assertJsonPath('data.0.url', ContentPageCatalog::publicWebUrl('en', 'privacy-policy'))
            ->assertHeader('ETag');

        $this->assertCount(1, $response->json('data'));
        $this->assertMatchesRegularExpression('/Z$/', (string) $response->json('data.0.updated_at'));
    }

    public function test_page_show_returns_localized_body(): void
    {
        ContentPage::query()->create([
            'slug' => ContentPageCatalog::SLUG_TERMS_OF_SERVICE,
            'title_en' => 'Terms of Service',
            'title_ar' => 'شروط الاستخدام',
            'body_en' => '<p>EN terms body</p>',
            'body_ar' => '<p>AR terms body</p>',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/pages/terms-of-service?locale=ar')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'terms-of-service')
            ->assertJsonPath('data.title', 'شروط الاستخدام')
            ->assertJsonPath('data.body', '<p>AR terms body</p>')
            ->assertJsonPath('data.category', ContentPageCatalog::CATEGORY_LEGAL)
            ->assertJsonPath('data.url', ContentPageCatalog::publicWebUrl('ar', 'terms-of-service'));
    }

    public function test_locale_can_be_resolved_from_accept_language(): void
    {
        ContentPage::query()->create([
            'slug' => ContentPageCatalog::SLUG_PRIVACY_POLICY,
            'title_en' => 'Privacy Policy',
            'title_ar' => 'سياسة الخصوصية',
            'body_en' => '<p>EN</p>',
            'body_ar' => '<p>AR</p>',
            'is_active' => true,
        ]);

        $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/v1/pages/privacy-policy')
            ->assertOk()
            ->assertJsonPath('data.title', 'سياسة الخصوصية')
            ->assertJsonPath('data.body', '<p>AR</p>');
    }

    public function test_inactive_or_missing_page_returns_404(): void
    {
        ContentPage::query()->create([
            'slug' => 'hidden',
            'title_en' => 'Hidden',
            'title_ar' => 'مخفي',
            'body_en' => 'Secret',
            'body_ar' => 'سر',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/pages/hidden')
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->getJson('/api/v1/pages/missing')
            ->assertNotFound();
    }

    public function test_pages_can_be_filtered_by_category_and_product(): void
    {
        $this->createLegalPage();
        $this->createProductPolicy(ContentPageCatalog::PRODUCT_FLIGHT, '<p>Flight body EN</p>');
        $this->createProductPolicy(ContentPageCatalog::PRODUCT_HOTEL, '<p>Hotel body EN</p>');

        $this->getJson('/api/v1/pages?category=legal')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'privacy-policy');

        $this->getJson('/api/v1/pages?category=product_policy')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/pages?product=flight')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'flight-policy')
            ->assertJsonPath('data.0.product', 'flight')
            ->assertJsonPath('data.0.category', 'product_policy');
    }

    public function test_invalid_page_filters_return_validation_error(): void
    {
        $this->getJson('/api/v1/pages?category=marketing')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed');

        $this->getJson('/api/v1/pages?product=cars')
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed');
    }

    public function test_product_policy_endpoint_returns_localized_body(): void
    {
        $this->createProductPolicy(ContentPageCatalog::PRODUCT_FLIGHT, '<p>Flight body EN</p>', '<p>نص سياسة الطيران</p>');

        $this->getJson('/api/v1/pages/product/flight?locale=ar')
            ->assertOk()
            ->assertJsonPath('data.slug', 'flight-policy')
            ->assertJsonPath('data.product', 'flight')
            ->assertJsonPath('data.category', 'product_policy')
            ->assertJsonPath('data.body', '<p>نص سياسة الطيران</p>')
            ->assertJsonPath('data.url', ContentPageCatalog::publicWebUrl('ar', 'flight-policy'));
    }

    public function test_page_url_is_returned_when_set(): void
    {
        ContentPage::query()->create([
            'slug' => ContentPageCatalog::SLUG_PRIVACY_POLICY,
            'title_en' => 'Privacy Policy',
            'title_ar' => 'سياسة الخصوصية',
            'body_en' => '<p>Body</p>',
            'body_ar' => '<p>المحتوى</p>',
            'url' => 'https://example.com/privacy',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/pages/privacy-policy')
            ->assertOk()
            ->assertJsonPath('data.url', 'https://example.com/privacy');
    }

    public function test_missing_or_unknown_product_policy_returns_404(): void
    {
        $this->getJson('/api/v1/pages/product/flight')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->getJson('/api/v1/pages/product/cars')
            ->assertNotFound();
    }

    public function test_workspace_endpoint_returns_grouped_legal_and_product_policies(): void
    {
        $this->createLegalPage();
        $this->createProductPolicy(ContentPageCatalog::PRODUCT_FLIGHT, '<p>Flight body EN</p>');
        $this->createProductPolicy(ContentPageCatalog::PRODUCT_HOTEL, '<p>Hotel body EN</p>');

        $this->getJson('/api/v1/pages/workspace?locale=en')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.legal.privacy-policy.slug', 'privacy-policy')
            ->assertJsonPath('data.legal.privacy-policy.url', ContentPageCatalog::publicWebUrl('en', 'privacy-policy'))
            ->assertJsonPath('data.products.flight.slug', 'flight-policy')
            ->assertJsonPath('data.products.flight.product', 'flight')
            ->assertJsonPath('data.products.hotel.slug', 'hotel-policy')
            ->assertJsonMissingPath('data.products.insurance')
            ->assertHeader('ETag');
    }

    private function createLegalPage(): ContentPage
    {
        return ContentPage::query()->create([
            'slug' => ContentPageCatalog::SLUG_PRIVACY_POLICY,
            'category' => ContentPageCatalog::CATEGORY_LEGAL,
            'title_en' => 'Privacy Policy',
            'title_ar' => 'سياسة الخصوصية',
            'body_en' => '<p>EN privacy body</p>',
            'body_ar' => '<p>سياسة الخصوصية</p>',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createProductPolicy(string $product, string $bodyEn, string $bodyAr = '<p>AR policy body</p>'): ContentPage
    {
        $slug = ContentPageCatalog::slugForProduct($product) ?? $product.'-policy';

        return ContentPage::query()->create([
            'slug' => $slug,
            'category' => ContentPageCatalog::CATEGORY_PRODUCT_POLICY,
            'product' => $product,
            'title_en' => ucfirst($product).' Policies',
            'title_ar' => 'سياسات',
            'body_en' => $bodyEn,
            'body_ar' => $bodyAr,
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }
}
