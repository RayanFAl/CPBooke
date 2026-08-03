<?php

namespace App\Modules\Admin\Support\Http\Requests;

use App\Modules\Support\Storage\SupportAttachmentRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportReplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:10000', 'required_without:attachment'],
            'attachment' => SupportAttachmentRules::fileRules(),
        ];
    }
}
