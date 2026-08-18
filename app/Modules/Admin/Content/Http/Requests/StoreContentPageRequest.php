<?php

namespace App\Modules\Admin\Content\Http\Requests;

use App\Modules\Admin\Content\Http\Requests\Concerns\NormalizesContentPagePayload;
use App\Modules\Content\Support\ContentPageCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentPageRequest extends FormRequest
{
    use NormalizesContentPagePayload;

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
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('content_pages', 'slug')],
            'category' => ['required', 'string', Rule::in(ContentPageCatalog::categories())],
            'product' => ContentPageCatalog::productValidationRule(),
            'title_en' => ['required', 'string', 'max:160'],
            'title_ar' => ['required', 'string', 'max:160'],
            'body_en' => ['required', 'string'],
            'body_ar' => ['required', 'string'],
            'url' => ContentPageCatalog::urlValidationRule(),
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
