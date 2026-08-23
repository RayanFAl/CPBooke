<?php

namespace App\Modules\Admin\MobileApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadMobileApkRequest extends FormRequest
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
        $maxKb = max(1, (int) config('mobile_app.max_upload_kb', 512000));

        return [
            'apk' => ['required', 'file', 'extensions:apk', 'max:'.$maxKb],
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
            'version_code' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.regex' => 'Version must use semantic format like 1.2.0.',
            'apk.extensions' => 'The uploaded file must be an Android APK.',
        ];
    }
}
