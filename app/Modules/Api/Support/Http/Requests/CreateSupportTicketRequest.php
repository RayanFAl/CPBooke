<?php

namespace App\Modules\Api\Support\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class CreateSupportTicketRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isCustomerAccount() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'category' => ['required', 'string', Rule::in([
                'booking_change',
                'refund_request',
                'technical_issue',
                'payment_issue',
                'document_request',
            ])],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required_without:attachment', 'nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,heic,heif,pdf,doc,docx,xls,xlsx,csv,txt,mp4,mov,m4v,3gp,avi,webm', 'required_without:message'],
        ];
    }
}