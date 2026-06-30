<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeaturedAirport extends Model
{
    public const MAX_COUNT = 10;

    protected $fillable = [
        'airport_key',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
