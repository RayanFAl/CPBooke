<?php

namespace App\Modules\Admin\Content\Services;

use App\Models\ContentPage;
use Illuminate\Support\Str;

class ContentPageAdminService
{
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
            'title_en' => $page->title_en,
            'title_ar' => $page->title_ar,
            'body_en' => $page->body_en,
            'body_ar' => $page->body_ar,
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

        return [
            'slug' => $slug,
            'title_en' => trim((string) ($data['title_en'] ?? '')),
            'title_ar' => $this->nullableString($data['title_ar'] ?? null),
            'body_en' => (string) ($data['body_en'] ?? ''),
            'body_ar' => $this->nullableString($data['body_ar'] ?? null),
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
