<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelReview extends Model
{
    public const CATEGORY_KEYS = [
        'cleanliness',
        'location',
        'service',
        'comfort',
        'value',
    ];

    protected $fillable = [
        'user_id',
        'order_id',
        'hotel_id',
        'booking_reference',
        'overall_rating',
        'categories',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'categories' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
