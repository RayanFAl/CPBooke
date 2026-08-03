<?php

namespace App\Modules\Admin\Support\Http\Requests;

use App\Modules\Api\Orders\Services\OrderActionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompensationSupportOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('support.partial-refund') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'compensation_type' => ['required', 'string', Rule::in(OrderActionService::compensationTypes())],
        ];
    }
}
