<?php

namespace App\Modules\Admin\MobileApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMobileReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
            'version_code' => ['required', 'integer', 'min:1'],
            'apk' => ['required', 'string', 'max:255', 'regex:/^[\w.\-+]+\.apk$/i'],
            'force_update' => ['sometimes', 'boolean'],
            'notify_users' => ['sometimes', 'boolean'],
            'min_version_code' => ['nullable', 'integer', 'min:1'],
            'notes_ar' => ['nullable', 'string', 'max:5000'],
            'notes_en' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.regex' => 'Version must use semantic format like 1.2.0.',
            'apk.regex' => 'APK filename must end with .apk.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('force_update')) {
            $this->merge([
                'force_update' => filter_var($this->input('force_update'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('notify_users')) {
            $this->merge([
                'notify_users' => filter_var($this->input('notify_users'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->input('min_version_code') === '' || $this->input('min_version_code') === null) {
            $this->merge(['min_version_code' => null]);
        }
    }
}
