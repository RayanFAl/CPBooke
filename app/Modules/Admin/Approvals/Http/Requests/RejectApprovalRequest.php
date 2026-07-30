<?php

namespace App\Modules\Admin\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approvals.approve') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
