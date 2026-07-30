<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'check_key',
    'status',
    'latency_ms',
    'message',
    'meta',
    'created_at',
])]
class SystemHealthCheck extends Model
{
    public $timestamps = false;

    public const STATUS_OK = 'ok';

    public const STATUS_WARN = 'warn';

    public const STATUS_FAIL = 'fail';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
