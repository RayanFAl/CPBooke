<?php

namespace App\Modules\Api\Home\Services;

use App\Models\HomeBanner;
use App\Models\HomeOffer;
use App\Support\Platform\PlatformSettings;
use Illuminate\Http\Request;

class HomeContentService
{
    /**
     * @return array{banners: list<array<string, mixed>>, offers: list<array<string, mixed>>}
     */
    public function content(Request $request): array
    {
        $locale = $this->resolveLocale($request);
        $platform = $this->resolvePlatform($request);
        $offerLimit = min(max($request->integer('limit', 20), 1), 50);

        return [
            'banners' => $this->banners($locale, $platform),
            'offers' => $this->offers($locale, $platform, $offerLimit),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function banners(?string $locale = null, ?string $platform = null): array
    {
        $locale ??= 'en';

        return HomeBanner::query()
            ->currentlyVisible()
            ->forPlatform($platform)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HomeBanner $banner): array => $this->mapBanner($banner, $locale))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function offers(?string $locale = null, ?string $platform = null, int $limit = 20): array
    {
        if (! PlatformSettings::homeOffersEnabled()) {
            return [];
        }

        $locale ??= 'en';
        $limit = min(max($limit, 1), 50);

        return HomeOffer::query()
            ->currentlyVisible()
            ->forPlatform($platform)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (HomeOffer $offer): array => $this->mapOffer($offer, $locale))
            ->values()
            ->all();
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

    public function resolvePlatform(Request $request): ?string
    {
        $platform = strtolower(trim($request->string('platform')->toString()));

        return in_array($platform, ['ios', 'android'], true) ? $platform : null;
    }

    /**
     * @param  list<array<string, mixed>>|array{banners: list<array<string, mixed>>, offers: list<array<string, mixed>>}  $payload
     */
    public function etagFor(array $payload): string
    {
        return '"'.sha1((string) json_encode($payload)).'"';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBanner(HomeBanner $banner, string $locale): array
    {
        $item = [
            'id' => $banner->public_id,
            'title' => $banner->localizedTitle($locale),
            'subtitle' => $banner->localizedSubtitle($locale),
            'image_url' => $banner->resolvedImageUrl(),
            'action_type' => $banner->action_type,
            'action_value' => $banner->action_value,
            'sort_order' => $banner->sort_order,
            'is_active' => $banner->is_active,
            'starts_at' => $banner->starts_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'ends_at' => $banner->ends_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];

        return $this->withActionPayload($item, $banner->action_type, $banner->action_payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOffer(HomeOffer $offer, string $locale): array
    {
        $item = [
            'id' => $offer->public_id,
            'title' => $offer->localizedTitle($locale),
            'subtitle' => $offer->localizedSubtitle($locale),
            'badge' => $offer->localizedBadge($locale),
            'image_url' => $offer->resolvedImageUrl(),
            'accent_color' => $offer->accent_color,
            'category' => $offer->category,
            'action_type' => $offer->action_type,
            'action_value' => $offer->action_value,
            'sort_order' => $offer->sort_order,
            'is_active' => $offer->is_active,
            'starts_at' => $offer->starts_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'ends_at' => $offer->ends_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];

        return $this->withActionPayload($item, $offer->action_type, $offer->action_payload);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function withActionPayload(array $item, string $actionType, ?array $payload): array
    {
        if (str_starts_with($actionType, 'search_') && is_array($payload) && $payload !== []) {
            $item['action_payload'] = $payload;
        }

        return $item;
    }
}
