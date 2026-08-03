<?php

namespace App\Modules\Api\Support\Http\Requests;

use App\Modules\Support\Storage\SupportAttachmentRules;

class StoreSupportReplyRequest extends ApiFormRequest
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
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required_without:attachment', 'nullable', 'string', 'max:5000'],
            'attachment' => SupportAttachmentRules::fileRules(),
        ];
    }
}
