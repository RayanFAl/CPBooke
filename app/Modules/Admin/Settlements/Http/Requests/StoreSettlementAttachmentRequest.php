<?php

namespace App\Modules\Admin\Settlements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSettlementAttachmentRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx,xlsm,pdf'],
        ];
    }
}
