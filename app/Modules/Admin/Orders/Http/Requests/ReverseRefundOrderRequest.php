<?php

namespace App\Modules\Admin\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReverseRefundOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance.reverse-refund') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'internal_note' => ['required', 'string', 'max:1000'],
        ];
    }
}
