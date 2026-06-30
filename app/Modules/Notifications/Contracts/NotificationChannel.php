<?php

namespace App\Modules\Notifications\Contracts;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;

interface NotificationChannel
{
    public function channel(): string;

    /**
     * Deliver the notification through the current channel.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function send(NotificationLog $log, NotificationTemplate $template, User $user, array $variables): array;
}