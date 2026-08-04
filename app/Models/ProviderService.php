<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_id',
    'service',
    'enabled',
    'configuration',
])]
class ProviderService extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'configuration' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return array<string, string>
     */
    public static function serviceLabels(): array
    {
        return config('provider_api.services', []);
    }

    /**
     * @return array<int, string>
     */
    public static function serviceKeys(): array
    {
        return array_keys(self::serviceLabels());
    }
}
