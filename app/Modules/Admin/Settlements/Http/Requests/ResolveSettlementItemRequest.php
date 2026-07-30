<?php

namespace App\Modules\Admin\Settlements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveSettlementItemRequest extends FormRequest
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
            'resolution_note' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
