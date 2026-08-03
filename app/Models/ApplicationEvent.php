<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'category',
    'severity',
    'source',
    'message',
    'context',
    'created_at',
])]
class ApplicationEvent extends Model
{
    public $timestamps = false;

    public const CATEGORY_EXCEPTION = 'exception';

    public const CATEGORY_SLOW_REQUEST = 'slow_request';

    public const CATEGORY_API_ERROR = 'api_error';

    public const CATEGORY_QUEUE = 'queue';

    public const CATEGORY_SYSTEM = 'system';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
