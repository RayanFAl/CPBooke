<?php

namespace App\Modules\Admin\Partners\Http\Requests;

use App\Modules\Partners\Support\PartnerWebhookEvents;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerWebhookRequest extends FormRequest
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
            'url' => ['required', 'url', 'max:500', 'starts_with:https://,http://'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in(PartnerWebhookEvents::all())],
            'description' => ['nullable', 'string', 'max:190'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
