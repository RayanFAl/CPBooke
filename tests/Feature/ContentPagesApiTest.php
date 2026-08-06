<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPagesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_index_is_public_and_returns_active_pages_only(): void
    {
        ContentPage::query()->create([
            'slug' => 'privacy-policy',
            'title_en' => 'Privacy Policy',
            'title_ar' => 'سياسة الخصوصية',
            'body_en' => 'EN privacy body',
            'body_ar' => 'AR privacy body',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        ContentPage::query()->create([
            'slug' => 'draft-page',
            'title_en' => 'Draft',
            'body_en' => 'Hidden',
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/pages?locale=en')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.slug', 'privacy-policy')
            ->assertJsonPath('data.0.title', 'Privacy Policy')
            ->assertHeader('ETag');

        $this->assertCount(1, $response->json('data'));
        $this->assertArrayNotHasKey('body', $response->json('data.0'));
    }

    public function test_page_show_returns_localized_body(): void
    {
        ContentPage::query()->create([
            'slug' => 'terms-of-service',
            'title_en' => 'Terms of Service',
            'title_ar' => 'شروط الاستخدام',
            'body_en' => 'EN terms body',
            'body_ar' => 'AR terms body',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/pages/terms-of-service?locale=ar')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'terms-of-service')
            ->assertJsonPath('data.title', 'شروط الاستخدام')
            ->assertJsonPath('data.body', 'AR terms body');
    }

    public function test_inactive_or_missing_page_returns_404(): void
    {
        ContentPage::query()->create([
            'slug' => 'hidden',
            'title_en' => 'Hidden',
            'body_en' => 'Secret',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/pages/hidden')
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->getJson('/api/v1/pages/missing')
            ->assertNotFound();
    }
}
