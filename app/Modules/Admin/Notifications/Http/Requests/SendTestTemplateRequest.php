<?php

namespace App\Modules\Admin\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendTestTemplateRequest extends FormRequest
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
            'template_code' => [
                'required',
                'string',
                'max:80',
                Rule::when(
                    strtoupper((string) $this->input('template_code')) !== 'ALL',
                    Rule::exists('notification_templates', 'code'),
                ),
            ],
            'include_email' => ['sometimes', 'boolean'],
        ];
    }
}
