<?php

namespace App\Modules\Api\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkNotificationsReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notification_ids' => ['array', 'required_without:mark_all'],
            'notification_ids.*' => ['integer', 'exists:user_notifications,id'],
            'mark_all' => ['boolean', 'required_without:notification_ids'],
        ];
    }
}