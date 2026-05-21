<?php

namespace App\Modules\Admin\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderNotesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the request data for validation.
     */
    protected function prepareForValidation(): void
    {
        $notes = $this->input('internal_notes');

        $this->merge([
            'internal_notes' => is_string($notes) && trim($notes) === ''
                ? null
                : $notes,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}