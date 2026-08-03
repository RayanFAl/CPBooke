<?php

namespace App\Modules\Api\Notifications\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use App\Modules\Notifications\Support\NotificationTopics;

class UpdateNotificationPreferencesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $topicRules = [];
        foreach (NotificationTopics::keys() as $topic) {
            $topicRules[$topic] = ['sometimes', 'boolean'];
        }

        return [
            'push' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'boolean'],
            'sms' => ['sometimes', 'boolean'],
            ...$topicRules,
        ];
    }
}
