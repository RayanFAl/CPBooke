<?php

namespace App\Modules\Admin\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundSupportOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($this->routeIs('admin.support.order.full-refund')) {
            return $user->can('support.full-refund');
        }

        if ($this->routeIs('admin.support.order.partial-refund')) {
            return $user->can('support.partial-refund');
        }

        return $user->can('support.full-refund') || $user->can('support.partial-refund');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
