<?php

namespace App\Modules\Admin\MobileApp\Http\Requests;

use App\Support\PhpIniSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'apk' => ['required', 'file', 'extensions:apk,zip', 'max:'.$maxKb],
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
            'version_code' => ['required', 'integer', 'min:1'],
            'notify_users' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notify_users')) {
            $this->merge([
                'notify_users' => filter_var($this->input('notify_users'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->file('apk') !== null) {
                return;
            }

            $contentLength = (int) ($this->server('CONTENT_LENGTH') ?? 0);

            if ($contentLength <= 0) {
                return;
            }

            $postMaxBytes = PhpIniSize::toBytes((string) ini_get('post_max_size'));
            $uploadMaxBytes = PhpIniSize::toBytes((string) ini_get('upload_max_filesize'));

            if ($contentLength > $postMaxBytes) {
                $validator->errors()->add(
                    'apk',
                    'The APK exceeds PHP post_max_size ('.ini_get('post_max_size').'). Increase PHP limits or run: php artisan mobile-app:import-apk path/to/app.apk',
                );

                return;
            }

            if ($contentLength > $uploadMaxBytes) {
                $validator->errors()->add(
                    'apk',
                    'The APK exceeds PHP upload_max_filesize ('.ini_get('upload_max_filesize').'). Increase PHP limits or run: php artisan mobile-app:import-apk path/to/app.apk',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.regex' => 'Version must use semantic format like 1.2.0.',
            'apk.extensions' => 'The uploaded file must be an Android APK or a ZIP that contains one.',
            'apk.max' => 'The APK/ZIP is too large for the configured upload limit.',
        ];
    }
}
