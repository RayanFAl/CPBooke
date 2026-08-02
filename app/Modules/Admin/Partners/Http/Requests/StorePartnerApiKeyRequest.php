<?php

namespace App\Modules\Admin\Partners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('partners.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}
