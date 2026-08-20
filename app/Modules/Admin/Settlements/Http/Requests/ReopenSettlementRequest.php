<?php

namespace App\Modules\Admin\Settlements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReopenSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settlements.reopen') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
