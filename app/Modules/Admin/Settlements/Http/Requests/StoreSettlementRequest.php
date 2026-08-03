<?php

namespace App\Modules\Admin\Settlements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settlements.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
