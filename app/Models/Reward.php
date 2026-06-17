<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'value',
        'probability',
        'daily_limit',
        'inventory_limit',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'probability' => 'decimal:2',
        'daily_limit' => 'integer',
        'inventory_limit' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the spins that won this reward.
     *
     * @return HasMany<Spin, $this>
     */
    public function spins(): HasMany
    {
        return $this->hasMany(Spin::class);
    }

    /**
     * Get the claims created for this reward.
     *
     * @return HasMany<RewardClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(RewardClaim::class);
    }
}
