<?php

namespace App\Modules\Api\Support\Http\Requests;

use App\Modules\Support\SupportAttachmentRules;

class StoreSupportChatMessageRequest extends ApiFormRequest
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
            'message' => ['required_without:attachment', 'nullable', 'string', 'max:5000'],
            'attachment' => SupportAttachmentRules::attachmentFieldRules(),
            'reply_to_id' => ['nullable', 'integer', 'exists:support_messages,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
