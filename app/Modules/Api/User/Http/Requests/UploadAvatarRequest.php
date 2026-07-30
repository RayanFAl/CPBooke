<?php

namespace App\Modules\Api\User\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class UploadAvatarRequest extends ApiFormRequest
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
        $maxKb = max(100, (int) config('profile.avatar_max_kilobytes', 2048));

        return [
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKb],
        ];
    }
}
