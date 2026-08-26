<?php

namespace App\Modules\Api\LinkedAccounts\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class UpdateLinkedAccountPermissionsRequest extends ApiFormRequest
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
            'can_request_payment' => ['sometimes', 'boolean'],
            'can_receive_payment_requests' => ['sometimes', 'boolean'],
            'auto_approve' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->hasAny([
                'can_request_payment',
                'can_receive_payment_requests',
                'auto_approve',
            ])) {
                $validator->errors()->add(
                    'permissions',
                    'At least one permission field must be provided.',
                );
            }
        });
    }
}
