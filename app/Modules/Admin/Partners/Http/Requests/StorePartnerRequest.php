<?php

namespace App\Modules\Admin\Partners\Http\Requests;

use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('partners.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('slug') === '') {
            $this->merge(['slug' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('partners', 'slug')],
            'status' => ['required', Rule::in([Partner::STATUS_ACTIVE, Partner::STATUS_INACTIVE])],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
