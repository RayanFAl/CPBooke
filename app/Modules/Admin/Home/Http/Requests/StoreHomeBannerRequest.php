<?php

namespace App\Modules\Admin\Home\Http\Requests;

use App\Models\HomeBanner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHomeBannerRequest extends FormRequest
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
            'title_en' => ['required', 'string', 'max:160'],
            'title_ar' => ['nullable', 'string', 'max:160'],
            'subtitle_en' => ['nullable', 'string', 'max:255'],
            'subtitle_ar' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'action_type' => ['required', Rule::in(HomeBanner::ACTION_TYPES)],
            'action_value' => ['nullable', 'string', 'max:500'],
            'action_payload' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['in:ios,android'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
