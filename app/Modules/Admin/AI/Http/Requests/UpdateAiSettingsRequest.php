<?php

namespace App\Modules\Admin\AI\Http\Requests;

use App\Modules\AI\Services\AiSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AiSettingsService $aiSettings */
        $aiSettings = app(AiSettingsService::class);

        return [
            'enabled' => ['required', 'boolean'],
            'provider' => ['required', 'string', 'max:32', Rule::in(['gemini'])],
            'model' => ['required', 'string', 'max:64', Rule::in($aiSettings->availableModels())],
            'base_url' => ['required', 'url', 'max:255'],
            'timeout' => ['required', 'integer', 'min:3', 'max:60'],
            'max_output_tokens' => ['required', 'integer', 'min:128', 'max:8192'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_offers_for_recommendation' => ['required', 'integer', 'min:1', 'max:20'],
            'max_conversation_turns' => ['required', 'integer', 'min:0', 'max:20'],
            'timezone' => ['required', 'string', 'max:64'],
            'default_currency' => ['required', 'string', 'size:3'],
            'prefer_rules_nlu' => ['required', 'boolean'],
        ];
    }
}
