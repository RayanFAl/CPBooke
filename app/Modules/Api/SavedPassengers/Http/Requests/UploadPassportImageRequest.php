<?php

namespace App\Modules\Api\SavedPassengers\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class UploadPassportImageRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $maxKb = max(100, (int) config('saved-passengers.passport_image_max_kilobytes', 6144));

        return [
            'file' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.$maxKb,
            ],
        ];
    }
}
