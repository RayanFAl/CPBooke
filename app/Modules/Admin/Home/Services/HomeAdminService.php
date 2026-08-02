<?php

namespace App\Modules\Admin\Home\Services;

use App\Models\HomeBanner;
use App\Models\HomeOffer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomeAdminService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createBanner(array $data, ?UploadedFile $image = null): HomeBanner
    {
        $banner = new HomeBanner($this->normalizeSharedFields($data));
        $banner->public_id = $this->makePublicId('bnr');
        $this->applyImage($banner, $image, $data['image_url'] ?? null);
        $banner->save();

        return $banner;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateBanner(HomeBanner $banner, array $data, ?UploadedFile $image = null): HomeBanner
    {
        $banner->fill($this->normalizeSharedFields($data));
        $this->applyImage($banner, $image, $data['image_url'] ?? null, array_key_exists('image_url', $data));
        $banner->save();

        return $banner;
    }

    public function deleteBanner(HomeBanner $banner): void
    {
        $this->deleteStoredImage($banner->image_path);
        $banner->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createOffer(array $data, ?UploadedFile $image = null): HomeOffer
    {
        $offer = new HomeOffer(array_merge(
            $this->normalizeSharedFields($data),
            [
                'badge_en' => $data['badge_en'] ?? null,
                'badge_ar' => $data['badge_ar'] ?? null,
                'accent_color' => $data['accent_color'] ?? null,
                'category' => $data['category'] ?? 'other',
            ],
        ));
        $offer->public_id = $this->makePublicId('off');
        $this->applyImage($offer, $image, $data['image_url'] ?? null);
        $offer->save();

        return $offer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateOffer(HomeOffer $offer, array $data, ?UploadedFile $image = null): HomeOffer
    {
        $offer->fill(array_merge(
            $this->normalizeSharedFields($data),
            [
                'badge_en' => $data['badge_en'] ?? null,
                'badge_ar' => $data['badge_ar'] ?? null,
                'accent_color' => $data['accent_color'] ?? null,
                'category' => $data['category'] ?? $offer->category,
            ],
        ));
        $this->applyImage($offer, $image, $data['image_url'] ?? null, array_key_exists('image_url', $data));
        $offer->save();

        return $offer;
    }

    public function deleteOffer(HomeOffer $offer): void
    {
        $this->deleteStoredImage($offer->image_path);
        $offer->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeBanner(HomeBanner $banner): array
    {
        return [
            'id' => $banner->id,
            'public_id' => $banner->public_id,
            'title_en' => $banner->title_en,
            'title_ar' => $banner->title_ar,
            'subtitle_en' => $banner->subtitle_en,
            'subtitle_ar' => $banner->subtitle_ar,
            'image_path' => $banner->image_path,
            'image_url' => $banner->resolvedImageUrl(),
            'action_type' => $banner->action_type,
            'action_value' => $banner->action_value,
            'action_payload' => $banner->action_payload,
            'action_payload_json' => $banner->action_payload
                ? json_encode($banner->action_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : '',
            'sort_order' => $banner->sort_order,
            'is_active' => $banner->is_active,
            'starts_at' => $banner->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $banner->ends_at?->format('Y-m-d\TH:i'),
            'platforms' => $banner->platforms ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeOffer(HomeOffer $offer): array
    {
        return [
            'id' => $offer->id,
            'public_id' => $offer->public_id,
            'title_en' => $offer->title_en,
            'title_ar' => $offer->title_ar,
            'subtitle_en' => $offer->subtitle_en,
            'subtitle_ar' => $offer->subtitle_ar,
            'badge_en' => $offer->badge_en,
            'badge_ar' => $offer->badge_ar,
            'image_path' => $offer->image_path,
            'image_url' => $offer->resolvedImageUrl(),
            'accent_color' => $offer->accent_color,
            'category' => $offer->category,
            'action_type' => $offer->action_type,
            'action_value' => $offer->action_value,
            'action_payload' => $offer->action_payload,
            'action_payload_json' => $offer->action_payload
                ? json_encode($offer->action_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : '',
            'sort_order' => $offer->sort_order,
            'is_active' => $offer->is_active,
            'starts_at' => $offer->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $offer->ends_at?->format('Y-m-d\TH:i'),
            'platforms' => $offer->platforms ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeSharedFields(array $data): array
    {
        $payload = $data['action_payload'] ?? null;
        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($payload) || $payload === []) {
            $payload = null;
        }

        $platforms = $data['platforms'] ?? null;
        if (is_array($platforms)) {
            $platforms = array_values(array_intersect($platforms, ['ios', 'android']));
            $platforms = $platforms === [] ? null : $platforms;
        } else {
            $platforms = null;
        }

        return [
            'title_en' => $data['title_en'],
            'title_ar' => $data['title_ar'] ?? null,
            'subtitle_en' => $data['subtitle_en'] ?? null,
            'subtitle_ar' => $data['subtitle_ar'] ?? null,
            'action_type' => $data['action_type'] ?? 'none',
            'action_value' => $data['action_value'] ?? null,
            'action_payload' => $payload,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
            'platforms' => $platforms,
        ];
    }

    private function applyImage(HomeBanner|HomeOffer $model, ?UploadedFile $image, mixed $imageUrl, bool $updateUrl = true): void
    {
        if ($image instanceof UploadedFile) {
            $this->deleteStoredImage($model->image_path);
            $directory = $model instanceof HomeBanner ? 'home/banners' : 'home/offers';
            $model->image_path = $image->store($directory, 'public');
            $model->image_url = null;

            return;
        }

        if (! $updateUrl) {
            return;
        }

        $url = is_string($imageUrl) ? trim($imageUrl) : '';
        $model->image_url = $url !== '' ? $url : null;
    }

    private function deleteStoredImage(?string $path): void
    {
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function makePublicId(string $prefix): string
    {
        return $prefix.'_'.Str::lower((string) Str::ulid());
    }
}
