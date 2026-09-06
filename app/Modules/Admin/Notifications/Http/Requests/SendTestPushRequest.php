<?php

namespace App\Modules\Admin\Notifications\Http\Requests;

use App\Models\User;
use Closure;
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
            'user_id' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === 'all') {
                    return;
                }

                if (! is_numeric($value) || ! User::query()->whereKey((int) $value)->exists()) {
                    $fail(__('validation.exists', ['attribute' => $attribute]));
                }
            }],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $userId = $this->input('user_id');

        if (is_string($userId) && strtolower($userId) === 'all') {
            $this->merge(['user_id' => 'all']);
        }
    }
}
