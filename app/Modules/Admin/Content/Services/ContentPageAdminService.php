<?php

namespace App\Modules\Admin\Content\Services;

use App\Models\ContentPage;
use App\Modules\Content\Support\ContentPageCatalog;
use Illuminate\Support\Str;

class ContentPageAdminService
{
    public function ensureWorkspacePages(): void
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

            if (ContentPage::query()->where($match)->exists()) {
                continue;
            }

            ContentPage::query()->create([
                'slug' => $definition['slug'],
                'category' => $definition['category'],
                'product' => $definition['product'],
                'title_en' => $definition['title_en'],
                'title_ar' => $definition['title_ar'],
                'body_en' => $definition['body_en'],
                'body_ar' => $definition['body_ar'],
                'url' => null,
                'sort_order' => $definition['sort_order'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function workspaceTabs(): array
    {
        $this->ensureWorkspacePages();

        $pagesByTabId = ContentPage::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->keyBy(function (ContentPage $page): string {
                if ($page->category === ContentPageCatalog::CATEGORY_PRODUCT_POLICY && $page->product) {
                    return (string) $page->product;
                }

                return (string) $page->slug;
            });

        return collect(ContentPageCatalog::workspaceDefinitions())
            ->map(function (array $definition) use ($pagesByTabId): array {
                $page = $pagesByTabId->get($definition['tab_id']);

                return [
                    'tab_id' => $definition['tab_id'],
                    'group' => $definition['category'] === ContentPageCatalog::CATEGORY_LEGAL ? 'legal' : 'product',
                    'label' => $definition['label'],
                    'label_ar' => $definition['label_ar'],
                    'page' => $page !== null ? $this->serialize($page) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ContentPage
    {
        return ContentPage::query()->create($this->normalize($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ContentPage $page, array $data): ContentPage
    {
        $page->fill($this->normalize($data));
        $page->save();

        return $page;
    }

    public function delete(ContentPage $page): void
    {
        $page->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ContentPage $page): array
    {
        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'category' => $page->category,
            'product' => $page->product,
            'category_label' => ContentPageCatalog::categoryLabel((string) $page->category),
            'category_label_ar' => ContentPageCatalog::categoryLabel((string) $page->category, 'ar'),
            'product_label' => ContentPageCatalog::productLabel($page->product),
            'product_label_ar' => ContentPageCatalog::productLabel($page->product, 'ar'),
            'title_en' => $page->title_en,
            'title_ar' => $page->title_ar,
            'body_en' => $page->body_en,
            'body_ar' => $page->body_ar,
            'url' => $page->url,
            'web_url_en' => $page->webUrl('en'),
            'web_url_ar' => $page->webUrl('ar'),
            'sort_order' => $page->sort_order,
            'is_active' => $page->is_active,
            'updated_at' => optional($page->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $slug = Str::slug((string) ($data['slug'] ?? ''));

        $category = (string) ($data['category'] ?? ContentPageCatalog::CATEGORY_LEGAL);
        $product = $category === ContentPageCatalog::CATEGORY_PRODUCT_POLICY
            ? $this->nullableString($data['product'] ?? null)
            : null;

        if ($product !== null) {
            $slug = ContentPageCatalog::slugForProduct($product) ?? $slug;
        }

        return [
            'slug' => $slug,
            'category' => $category,
            'product' => $product,
            'title_en' => trim((string) ($data['title_en'] ?? '')),
            'title_ar' => trim((string) ($data['title_ar'] ?? '')),
            'body_en' => (string) ($data['body_en'] ?? ''),
            'body_ar' => (string) ($data['body_ar'] ?? ''),
            'url' => $this->nullableString($data['url'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
