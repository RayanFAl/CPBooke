<?php

namespace App\Modules\Admin\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelSupportOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}