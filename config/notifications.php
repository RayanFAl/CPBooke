<?php

$reminderMinutes = (int) env('SEAT_ALERT_REMINDER_MINUTES', 0);

return [

    /*
    |--------------------------------------------------------------------------
    | Seat-alert "still watching" reminder
    |--------------------------------------------------------------------------
    |
    | Active seat watches receive a periodic push while seats are not yet
    | available. Typical production values: 6, 12, or 24 hours.
    | Set SEAT_ALERT_REMINDER_MINUTES (e.g. 1) for a local test; when it is
    | 0/empty the hours setting is used.
    |
    */

    'seat_alert_reminder_hours' => max(1, (int) env('SEAT_ALERT_REMINDER_HOURS', 12)),

    'seat_alert_reminder_minutes' => $reminderMinutes > 0
        ? max(1, $reminderMinutes)
        : max(1, (int) env('SEAT_ALERT_REMINDER_HOURS', 12)) * 60,

];
