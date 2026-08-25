<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Support\NotificationLocales;

class NotificationLocaleResolver
{
    public function forUser(User $user): string
    {
        $preferred = $user->preferred_locale ?? null;

        if (is_string($preferred) && $preferred !== '') {
            return NotificationLocales::normalize($preferred);
        }

        return NotificationLocales::AR;
    }
}
