<?php

namespace App\Modules\Api\Content\Services;

use App\Models\ContentPage;
use App\Modules\Content\Support\ContentPageCatalog;
use Illuminate\Http\Request;

class ContentPageService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $locale = null, ?string $category = null, ?string $product = null): array
    {
        $locale ??= 'en';

        return ContentPage::query()
            ->active()
            ->forCategory($category)
            ->forProduct($product)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ContentPage $page): array => $this->mapPage($page, $locale))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug, ?string $locale = null): ?array
    {
        $locale ??= 'en';

        $page = ContentPage::query()
            ->active()
            ->where('slug', $slug)
            ->first();

        if ($page === null) {
            return null;
        }

        return $this->mapPage($page, $locale);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByProduct(string $product, ?string $locale = null): ?array
    {
        $locale ??= 'en';

        $page = ContentPage::query()
            ->active()
            ->forProduct($product)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($page === null) {
            return null;
        }

        return $this->mapPage($page, $locale);
    }

    /**
     * @return array{legal: array<string, array<string, mixed>>, products: array<string, array<string, mixed>>}
     */
    public function workspace(?string $locale = null): array
    {
        $locale ??= 'en';

        $legal = [];
        foreach (ContentPageCatalog::legalSlugs() as $slug) {
            $page = ContentPage::query()
                ->active()
                ->where('slug', $slug)
                ->first();

            if ($page !== null) {
                $legal[$slug] = $this->mapPage($page, $locale);
            }
        }

        $products = [];
        foreach (ContentPageCatalog::products() as $product) {
            $page = ContentPage::query()
                ->active()
                ->forProduct($product)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($page !== null) {
                $products[$product] = $this->mapPage($page, $locale);
            }
        }

        return [
            'legal' => $legal,
            'products' => $products,
        ];
    }

    public function resolveLocale(Request $request): string
    {
        $locale = strtolower(trim($request->string('locale')->toString()));

        if (in_array($locale, ['ar', 'en'], true)) {
            return $locale;
        }

        $accept = strtolower((string) $request->header('Accept-Language', ''));

        if (str_starts_with($accept, 'ar')) {
            return 'ar';
        }

        return 'en';
    }

    /**
     * @param  list<array<string, mixed>>|array<string, mixed>  $payload
     */
    public function etagFor(array $payload): string
    {
        return '"'.sha1((string) json_encode($payload)).'"';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPage(ContentPage $page, string $locale): array
    {
        return [
            'title' => $page->localizedTitle($locale),
            'body' => $page->localizedBody($locale),
            'category' => $page->category,
            'product' => $page->product,
            'slug' => $page->slug,
            'url' => $page->publicUrl($locale),
            'updated_at' => $page->publishedAt(),
        ];
    }
}
