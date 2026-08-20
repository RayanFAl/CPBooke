<?php

namespace App\Modules\Admin\Settlements\Http\Requests;

use App\Modules\Finance\Support\FinancialContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveSettlementItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settlements.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', Rule::in([
                FinancialContract::RESOLUTION_ACCEPT_VARIANCE,
                FinancialContract::RESOLUTION_CORRECT_DATA,
            ])],
            'reason' => ['required', 'string', Rule::in(array_keys(FinancialContract::adjustmentReasons()))],
            'resolution_note' => ['required', 'string', 'min:3', 'max:2000'],
            'amount' => ['nullable', 'numeric'],
            'booking_reference' => ['nullable', 'string', 'max:100'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'supplier_invoice_cost' => ['nullable', 'numeric', 'min:0'],
            'drop_invoice_line' => ['sometimes', 'boolean'],
        ];
    }
}
