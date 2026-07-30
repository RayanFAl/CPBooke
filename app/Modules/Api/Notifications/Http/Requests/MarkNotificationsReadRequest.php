<?php

namespace App\Modules\Api\Notifications\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class MarkNotificationsReadRequest extends ApiFormRequest
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
            'notification_ids' => ['array', 'required_without:mark_all'],
            'notification_ids.*' => ['integer', 'exists:user_notifications,id'],
            'mark_all' => ['boolean', 'required_without:notification_ids'],
        ];
    }
}
