<?php

namespace App\Modules\Admin\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundSupportOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}