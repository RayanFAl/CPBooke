<?php

return [

    'otp_length' => (int) env('PROFILE_OTP_LENGTH', 6),

    'otp_expire_minutes' => (int) env('PROFILE_OTP_EXPIRE_MINUTES', 10),

    'otp_max_attempts' => (int) env('PROFILE_OTP_MAX_ATTEMPTS', 5),

    'resend_throttle_seconds' => (int) env('PROFILE_OTP_RESEND_THROTTLE_SECONDS', 60),

    'avatar_max_kilobytes' => (int) env('PROFILE_AVATAR_MAX_KB', 2048),

];
