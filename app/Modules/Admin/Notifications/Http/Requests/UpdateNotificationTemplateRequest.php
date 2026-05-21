<?php

namespace App\Modules\Admin\Notifications\Http\Requests;

use App\Modules\Notifications\Support\NotificationChannels;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', Rule::in(NotificationChannels::all())],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:80'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}