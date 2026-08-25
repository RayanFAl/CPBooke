<?php

namespace App\Modules\Admin\Catalog\Http\Requests;

use App\Models\MobileCatalogType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/'],
            'title_en' => ['required', 'string', 'max:160'],
            'title_ar' => ['nullable', 'string', 'max:160'],
            'subtitle_en' => ['nullable', 'string', 'max:255'],
            'subtitle_ar' => ['nullable', 'string', 'max:255'],
            'options_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'options_image_url' => ['nullable', 'url', 'max:500'],
            'market_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'market_image_url' => ['nullable', 'url', 'max:500'],
            'show_in_options' => ['sometimes', 'boolean'],
            'show_in_market' => ['sometimes', 'boolean'],
            'action_type' => ['required', Rule::in(MobileCatalogType::ACTION_TYPES)],
            'action_value' => ['nullable', 'string', 'max:500'],
            'action_payload' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['in:ios,android'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $key = trim((string) $this->input('key', ''));

        $this->merge([
            'key' => $key !== '' ? $key : null,
            'is_active' => $this->boolean('is_active'),
            'show_in_options' => $this->boolean('show_in_options'),
            'show_in_market' => $this->boolean('show_in_market'),
        ]);
    }
}
