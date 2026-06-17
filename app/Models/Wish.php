<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Wish extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['submission_id', 'wish_category_id', 'title', 'description'];

    /**
     * Configure the activity log options for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('wish')
            ->dontLogEmptyChanges();
    }

    /**
     * Get the submission this wish belongs to.
     *
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * Get the category this wish belongs to.
     *
     * @return BelongsTo<WishCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WishCategory::class, 'wish_category_id');
    }
}
