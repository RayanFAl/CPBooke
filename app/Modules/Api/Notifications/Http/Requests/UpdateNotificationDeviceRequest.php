<?php

namespace App\Modules\Api\Notifications\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class UpdateNotificationDeviceRequest extends ApiFormRequest
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
        return [
            'device_token' => ['required', 'string', 'min:20', 'max:191'],
            'enabled' => ['required', 'boolean'],
        ];
    }
}
