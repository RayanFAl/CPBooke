<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use App\Modules\Content\Support\ContentPageCatalog;
use Illuminate\Database\Seeder;

class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ContentPageCatalog::workspaceDefinitions() as $definition) {
            $match = $definition['product'] !== null
                ? [
                    'category' => $definition['category'],
                    'product' => $definition['product'],
                ]
                : [
                    'slug' => $definition['slug'],
                ];

            ContentPage::query()->updateOrCreate(
                $match,
                [
                    'slug' => $definition['slug'],
                    'category' => $definition['category'],
                    'product' => $definition['product'],
                    'title_en' => $definition['title_en'],
                    'title_ar' => $definition['title_ar'],
                    'body_en' => $definition['body_en'],
                    'body_ar' => $definition['body_ar'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
