<?php

namespace App\Modules\Api\Content\Services;

use App\Models\ContentPage;
use Illuminate\Http\Request;

class ContentPageService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $locale = null): array
    {
        $locale ??= 'en';

        return ContentPage::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ContentPage $page): array => $this->mapSummary($page, $locale))
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

        return $this->mapDetail($page, $locale);
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
    private function mapSummary(ContentPage $page, string $locale): array
    {
        return [
            'slug' => $page->slug,
            'title' => $page->localizedTitle($locale),
            'updated_at' => optional($page->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDetail(ContentPage $page, string $locale): array
    {
        return [
            'slug' => $page->slug,
            'title' => $page->localizedTitle($locale),
            'body' => $page->localizedBody($locale),
            'updated_at' => optional($page->updated_at)?->toIso8601String(),
        ];
    }
}
