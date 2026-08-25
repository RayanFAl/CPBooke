<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirportStat extends Model
{
    protected $fillable = [
        'airport_key',
        'search_count',
        'travel_count',
        'last_searched_at',
        'last_traveled_at',
    ];

    protected function casts(): array
    {
        return [
            'search_count' => 'integer',
            'travel_count' => 'integer',
            'last_searched_at' => 'datetime',
            'last_traveled_at' => 'datetime',
        ];
    }
}
