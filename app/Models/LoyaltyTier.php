<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'level',
    'code',
    'name',
    'description',
    'badge_label',
    'color_token',
    'sort_order',
    'is_active',
    'is_default',
    'metadata',
])]
class LoyaltyTier extends Model
{
    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(LoyaltyBenefit::class, 'tier_id')
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(LoyaltyRule::class, 'tier_id')
            ->orderByDesc('priority')
            ->orderByDesc('id');
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(UserLoyaltyProfile::class, 'current_tier_id');
    }

    public function upcomingProfiles(): HasMany
    {
        return $this->hasMany(UserLoyaltyProfile::class, 'next_tier_id');
    }
}