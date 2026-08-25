<?php

namespace Tests\Feature;

use App\Models\MobileCatalogType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_is_public_and_splits_options_and_market(): void
    {
        MobileCatalogType::query()->create([
            'public_id' => 'cat_travel',
            'key' => 'travel_insurance',
            'title_en' => 'Travel insurance',
            'title_ar' => 'تأمين سفر',
            'options_image_url' => 'https://cdn.example.com/options/travel.jpg',
            'market_image_url' => 'https://cdn.example.com/market/travel.jpg',
            'action_type' => 'search_insurance',
            'action_value' => '/insurance/travel',
            'action_payload' => ['subtype' => 'travel'],
            'sort_order' => 1,
            'is_active' => true,
            'show_in_options' => true,
            'show_in_market' => true,
        ]);

        MobileCatalogType::query()->create([
            'public_id' => 'cat_hidden',
            'key' => 'hidden',
            'title_en' => 'Hidden',
            'action_type' => 'none',
            'sort_order' => 99,
            'is_active' => false,
            'show_in_options' => true,
            'show_in_market' => true,
        ]);

        MobileCatalogType::query()->create([
            'public_id' => 'cat_options_only',
            'key' => 'options_only',
            'title_en' => 'Options only',
            'title_ar' => 'خيارات فقط',
            'options_image_url' => 'https://cdn.example.com/options/only.jpg',
            'action_type' => 'route',
            'action_value' => '/options-only',
            'sort_order' => 2,
            'is_active' => true,
            'show_in_options' => true,
            'show_in_market' => false,
        ]);

        $response = $this->getJson('/api/v1/catalog?locale=ar')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.types.0.key', 'travel_insurance')
            ->assertJsonPath('data.types.0.title', 'تأمين سفر')
            ->assertJsonPath('data.options.0.image_url', 'https://cdn.example.com/options/travel.jpg')
            ->assertJsonPath('data.market.0.image_url', 'https://cdn.example.com/market/travel.jpg')
            ->assertJsonPath('data.types.0.action_payload.subtype', 'travel')
            ->assertHeader('ETag');

        $this->assertCount(2, $response->json('data.types'));
        $this->assertCount(2, $response->json('data.options'));
        $this->assertCount(1, $response->json('data.market'));

        $this->getJson('/api/v1/catalog/options?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'travel_insurance')
            ->assertJsonPath('data.0.title', 'Travel insurance')
            ->assertJsonPath('data.1.key', 'options_only');

        $this->getJson('/api/v1/catalog/market?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'travel_insurance')
            ->assertJsonCount(1, 'data');
    }
}
