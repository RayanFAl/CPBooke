<?php

namespace App\Modules\Api\Catalog\Services;

use App\Models\MobileCatalogType;
use Illuminate\Http\Request;

class CatalogService
{
    /**
     * @return array{types: list<array<string, mixed>>, options: list<array<string, mixed>>, market: list<array<string, mixed>>}
     */
    public function content(Request $request): array
    {
        $locale = $this->resolveLocale($request);
        $platform = $this->resolvePlatform($request);
        $types = $this->visibleTypes($platform);

        return [
            'types' => $types->map(fn (MobileCatalogType $type): array => $this->mapType($type, $locale))->values()->all(),
            'options' => $types
                ->filter(fn (MobileCatalogType $type): bool => $type->show_in_options)
                ->map(fn (MobileCatalogType $type): array => $this->mapTile($type, $locale, 'options'))
                ->values()
                ->all(),
            'market' => $types
                ->filter(fn (MobileCatalogType $type): bool => $type->show_in_market)
                ->map(fn (MobileCatalogType $type): array => $this->mapTile($type, $locale, 'market'))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function section(Request $request, string $section): array
    {
        $data = $this->content($request);

        return $section === 'market' ? $data['market'] : $data['options'];
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

    public function etagFor(array $payload): string
    {
        return '"'.sha1((string) json_encode($payload)).'"';
    }

    /**
     * @return \Illuminate\Support\Collection<int, MobileCatalogType>
     */
    private function visibleTypes(?string $platform)
    {
        return MobileCatalogType::query()
            ->where('is_active', true)
            ->when($platform, function ($query) use ($platform): void {
                $query->where(function ($builder) use ($platform): void {
                    $builder
                        ->whereNull('platforms')
                        ->orWhereJsonContains('platforms', $platform);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapType(MobileCatalogType $type, string $locale): array
    {
        return $this->withActionPayload([
            'id' => $type->public_id,
            'key' => $type->key,
            'title' => $type->localizedTitle($locale),
            'subtitle' => $type->localizedSubtitle($locale),
            'options_image_url' => $type->resolvedOptionsImageUrl(),
            'market_image_url' => $type->resolvedMarketImageUrl(),
            'show_in_options' => $type->show_in_options,
            'show_in_market' => $type->show_in_market,
            'action_type' => $type->action_type,
            'action_value' => $type->action_value,
            'sort_order' => $type->sort_order,
        ], $type);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTile(MobileCatalogType $type, string $locale, string $section): array
    {
        return $this->withActionPayload([
            'id' => $type->public_id,
            'key' => $type->key,
            'title' => $type->localizedTitle($locale),
            'subtitle' => $type->localizedSubtitle($locale),
            'image_url' => $section === 'market'
                ? $type->resolvedMarketImageUrl()
                : $type->resolvedOptionsImageUrl(),
            'section' => $section,
            'action_type' => $type->action_type,
            'action_value' => $type->action_value,
            'sort_order' => $type->sort_order,
        ], $type);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function withActionPayload(array $item, MobileCatalogType $type): array
    {
        if (is_array($type->action_payload) && $type->action_payload !== []) {
            $item['action_payload'] = $type->action_payload;
        }

        return $item;
    }
}
