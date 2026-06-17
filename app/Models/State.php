<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class State extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code'];

    /**
     * Get the LGAs under this state.
     *
     * @return HasMany<Lga, $this>
     */
    public function lgas(): HasMany
    {
        return $this->hasMany(Lga::class);
    }

    /**
     * Get the agent profiles assigned to this state.
     *
     * @return HasMany<AgentProfile, $this>
     */
    public function agentProfiles(): HasMany
    {
        return $this->hasMany(AgentProfile::class);
    }

    /**
     * Get the submissions under this state.
     *
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * Get campaigns active in this state.
     *
     * @return BelongsToMany<Campaign, $this>
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class);
    }
}
