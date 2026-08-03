<?php

namespace App\Modules\Admin\Settlements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportSettlementInvoiceRequest extends FormRequest
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
            'lines' => ['nullable', 'array'],
            'lines.*.booking_reference' => ['nullable', 'string', 'max:100'],
            'lines.*.order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'lines.*.amount' => ['required_with:lines', 'numeric', 'min:0'],
            'csv_text' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $lines = $this->input('lines', []);
            $csv = trim((string) $this->input('csv_text', ''));

            if ((! is_array($lines) || $lines === []) && $csv === '') {
                $validator->errors()->add('lines', 'Provide invoice lines or CSV text.');
            }
        });
    }
}
