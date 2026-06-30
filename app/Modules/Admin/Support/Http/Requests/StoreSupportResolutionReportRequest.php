<?php

namespace App\Modules\Admin\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportResolutionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'resolution_type' => [
                'required',
                'string',
                Rule::in([
                    'resolved',
                    'partially_resolved',
                    'escalated',
                    'duplicate',
                    'invalid',
                    'customer_cancelled',
                ]),
            ],
            'root_cause' => ['required', 'string', 'max:5000'],
            'actions_taken' => ['required', 'string', 'max:5000'],
            'resolution_summary' => ['required', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'customer_visible_notes' => ['nullable', 'string', 'max:5000'],
            'status_after' => ['required', 'string', Rule::in(['resolved', 'closed'])],
            'escalated' => ['nullable', 'boolean'],
            'satisfaction_requested' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}