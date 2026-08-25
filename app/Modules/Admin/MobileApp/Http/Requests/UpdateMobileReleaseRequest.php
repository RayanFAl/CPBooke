<?php

namespace App\Modules\Admin\MobileApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;

class UpdateMobileReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('force_update')) {
            $this->merge([
                'force_update' => filter_var($this->input('force_update'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? $this->boolean('force_update'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
            'version_code' => ['required', 'integer', 'min:1'],
            'apk' => ['required', 'string', 'max:255'],
            'force_update' => ['sometimes', 'boolean'],
            'min_version_code' => ['nullable', 'integer', 'min:0'],
            'notes_ar' => ['nullable', 'string', 'max:5000'],
            'notes_en' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $apk = (string) $this->input('apk', '');
            $directory = (string) config('mobile_app.releases_directory');
            $path = $directory.DIRECTORY_SEPARATOR.$apk;

            if ($apk !== '' && ! File::isFile($path)) {
                $validator->errors()->add('apk', 'The selected APK file does not exist on the server.');
            }
        });
    }
}
