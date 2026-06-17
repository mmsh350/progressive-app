<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Submission extends Model
{
    use HasFactory, Searchable, LogsActivity;

    protected $fillable = [
        'reference_number',
        'full_name',
        'phone_number',
        'email',
        'gender',
        'age_group',
        'state_id',
        'lga_id',
        'ward_id',
        'polling_unit',
        'voted_2023',
        'vote_2027',
        'occupation_id',
        'status',
        'agent_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'voted_2023' => 'boolean',
        'vote_2027' => 'boolean',
    ];

    /**
     * Get the search index name for the model.
     */
    public function searchableAs(): string
    {
        return 'submissions_index';
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'full_name' => $this->full_name,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'gender' => $this->gender,
            'age_group' => $this->age_group,
            'polling_unit' => $this->polling_unit,
            'status' => $this->status,
        ];
    }

    /**
     * Configure the activity log options for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('submission')
            ->dontLogEmptyChanges();
    }

    /**
     * Get the state this submission belongs to.
     *
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Get the LGA this submission belongs to.
     *
     * @return BelongsTo<Lga, $this>
     */
    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    /**
     * Get the ward this submission belongs to.
     *
     * @return BelongsTo<Ward, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Get the occupation this submission belongs to.
     *
     * @return BelongsTo<Occupation, $this>
     */
    public function occupation(): BelongsTo
    {
        return $this->belongsTo(Occupation::class);
    }

    /**
     * Get the agent who registered this submission, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Get the wish associated with the submission.
     *
     * @return HasOne<Wish, $this>
     */
    public function wish(): HasOne
    {
        return $this->hasOne(Wish::class);
    }

    /**
     * Get the image associated with the submission.
     *
     * @return HasOne<SubmissionImage, $this>
     */
    public function image(): HasOne
    {
        return $this->hasOne(SubmissionImage::class);
    }

    /**
     * Get the spin claim associated with the submission.
     *
     * @return HasOne<Spin, $this>
     */
    public function spin(): HasOne
    {
        return $this->hasOne(Spin::class);
    }

    /**
     * Get the reward claim associated with the submission.
     *
     * @return HasOne<RewardClaim, $this>
     */
    public function rewardClaim(): HasOne
    {
        return $this->hasOne(RewardClaim::class);
    }
}
