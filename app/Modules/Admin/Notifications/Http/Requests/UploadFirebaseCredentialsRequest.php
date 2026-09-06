<?php

namespace App\Modules\Admin\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFirebaseCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credentials' => ['required', 'file', 'max:100', 'mimes:json,txt'],
        ];
    }
}
