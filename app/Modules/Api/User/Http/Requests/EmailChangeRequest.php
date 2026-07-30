<?php

namespace App\Modules\Api\User\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class EmailChangeRequest extends ApiFormRequest
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
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }
}
