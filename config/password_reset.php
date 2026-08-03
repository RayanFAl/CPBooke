<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset OTP
    |--------------------------------------------------------------------------
    |
    | Passenger API password reset uses a short-lived email OTP, then a
    | one-time reset_token used to set the new password.
    |
    */

    'otp_length' => (int) env('PASSWORD_RESET_OTP_LENGTH', 6),

    'otp_expire_minutes' => (int) env('PASSWORD_RESET_OTP_EXPIRE_MINUTES', 10),

    'otp_max_attempts' => (int) env('PASSWORD_RESET_OTP_MAX_ATTEMPTS', 5),

    /*
    | Seconds a user must wait before requesting another OTP (resend).
    | Resend uses the same POST /auth/forgot-password endpoint.
    */
    'resend_throttle_seconds' => (int) env('PASSWORD_RESET_RESEND_THROTTLE_SECONDS', 60),

    'reset_token_expire_minutes' => (int) env('PASSWORD_RESET_TOKEN_EXPIRE_MINUTES', 15),

];
