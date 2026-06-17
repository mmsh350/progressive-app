<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'is_active', 'start_date', 'end_date'];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Get the states assigned to this campaign.
     *
     * @return BelongsToMany<State, $this>
     */
    public function states(): BelongsToMany
    {
        return $this->belongsToMany(State::class);
    }
}
