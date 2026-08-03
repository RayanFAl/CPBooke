<?php

namespace App\Models;

use App\Support\Home\Concerns\HasHomeContentVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeBanner extends Model
{
    use HasFactory;
    use HasHomeContentVisibility;

    public const ACTION_TYPES = [
        'none',
        'route',
        'url',
        'search_flights',
        'search_hotels',
        'search_insurance',
    ];

    protected $fillable = [
        'public_id',
        'title_en',
        'title_ar',
        'subtitle_en',
        'subtitle_ar',
        'image_path',
        'image_url',
        'action_type',
        'action_value',
        'action_payload',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
        'platforms',
    ];

    protected function casts(): array
    {
        return [
            'action_payload' => 'array',
            'platforms' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
