<?php

namespace App\Modules\Admin\Partners\Http\Requests;

use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends FormRequest
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
        /** @var Partner $partner */
        $partner = $this->route('partner');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('partners', 'slug')->ignore($partner->id)],
            'status' => ['required', Rule::in([Partner::STATUS_ACTIVE, Partner::STATUS_INACTIVE])],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
