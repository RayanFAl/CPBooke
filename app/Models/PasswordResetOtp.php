<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    protected $fillable = [
        'email',
        'otp_hash',
        'otp_expires_at',
        'otp_attempts',
        'last_sent_at',
        'reset_token_hash',
        'reset_token_expires_at',
        'otp_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'reset_token_expires_at' => 'datetime',
            'otp_verified_at' => 'datetime',
            'otp_attempts' => 'integer',
        ];
    }
}
