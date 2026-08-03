<?php

namespace App\Modules\Admin\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendTestPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
        ];
    }
}
