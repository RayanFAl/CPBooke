<?php

namespace App\Modules\Admin\Catalog\Services;

use App\Models\MobileCatalogType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogAdminService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $optionsImage = null, ?UploadedFile $marketImage = null): MobileCatalogType
    {
        $type = new MobileCatalogType($this->normalizeFields($data));
        $type->public_id = 'cat_'.Str::lower((string) Str::ulid());
        $type->key = $this->uniqueKey($data['key'] ?? $data['title_en'] ?? 'type');
        $this->applyImage($type, 'options', $optionsImage, $data['options_image_url'] ?? null);
        $this->applyImage($type, 'market', $marketImage, $data['market_image_url'] ?? null);
        $type->save();

        return $type;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(
        MobileCatalogType $type,
        array $data,
        ?UploadedFile $optionsImage = null,
        ?UploadedFile $marketImage = null,
    ): MobileCatalogType {
        $type->fill($this->normalizeFields($data, $type));
        $type->key = $this->uniqueKey($data['key'] ?? $type->key, $type->id);
        $this->applyImage(
            $type,
            'options',
            $optionsImage,
            $data['options_image_url'] ?? null,
            array_key_exists('options_image_url', $data),
        );
        $this->applyImage(
            $type,
            'market',
            $marketImage,
            $data['market_image_url'] ?? null,
            array_key_exists('market_image_url', $data),
        );
        $type->save();

        return $type;
    }

    public function delete(MobileCatalogType $type): void
    {
        $this->deleteStoredImage($type->options_image_path);
        $this->deleteStoredImage($type->market_image_path);
        $type->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(MobileCatalogType $type): array
    {
        return [
            'id' => $type->id,
            'public_id' => $type->public_id,
            'key' => $type->key,
            'title_en' => $type->title_en,
            'title_ar' => $type->title_ar,
            'subtitle_en' => $type->subtitle_en,
            'subtitle_ar' => $type->subtitle_ar,
            'options_image_path' => $type->options_image_path,
            'options_image_url' => $type->resolvedOptionsImageUrl(),
            'market_image_path' => $type->market_image_path,
            'market_image_url' => $type->resolvedMarketImageUrl(),
            'show_in_options' => $type->show_in_options,
            'show_in_market' => $type->show_in_market,
            'action_type' => $type->action_type,
            'action_value' => $type->action_value,
            'action_payload' => $type->action_payload,
            'action_payload_json' => $type->action_payload
                ? json_encode($type->action_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : '',
            'sort_order' => $type->sort_order,
            'is_active' => $type->is_active,
            'platforms' => $type->platforms ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeFields(array $data, ?MobileCatalogType $existing = null): array
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
            $platforms = $existing?->platforms;
        }

        return [
            'title_en' => $data['title_en'],
            'title_ar' => $data['title_ar'] ?? null,
            'subtitle_en' => $data['subtitle_en'] ?? null,
            'subtitle_ar' => $data['subtitle_ar'] ?? null,
            'show_in_options' => (bool) ($data['show_in_options'] ?? true),
            'show_in_market' => (bool) ($data['show_in_market'] ?? true),
            'action_type' => $data['action_type'] ?? 'route',
            'action_value' => $data['action_value'] ?? null,
            'action_payload' => $payload,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'platforms' => $platforms,
        ];
    }

    private function applyImage(
        MobileCatalogType $type,
        string $slot,
        ?UploadedFile $image,
        mixed $imageUrl,
        bool $updateUrl = true,
    ): void {
        $pathField = "{$slot}_image_path";
        $urlField = "{$slot}_image_url";

        if ($image instanceof UploadedFile) {
            $this->deleteStoredImage($type->{$pathField});
            $type->{$pathField} = $image->store("catalog/{$slot}", 'public');
            $type->{$urlField} = null;

            return;
        }

        if (! $updateUrl) {
            return;
        }

        $url = is_string($imageUrl) ? trim($imageUrl) : '';
        $type->{$urlField} = $url !== '' ? $url : null;
    }

    private function uniqueKey(string $desired, ?int $ignoreId = null): string
    {
        $normalized = strtolower(trim($desired));
        $base = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?? '';
        $base = trim($base, '-_');
        if ($base === '') {
            $base = 'type';
        }

        $key = $base;
        $suffix = 2;

        while (
            MobileCatalogType::query()
                ->where('key', $key)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $key = $base.'-'.$suffix;
            $suffix++;
        }

        return $key;
    }

    private function deleteStoredImage(?string $path): void
    {
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
