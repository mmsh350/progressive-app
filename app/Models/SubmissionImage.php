<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionImage extends Model
{
    use HasFactory;

    protected $fillable = ['submission_id', 'image_path', 'watermark_applied'];

    protected $casts = [
        'watermark_applied' => 'boolean',
    ];

    /**
     * Get the submission this image belongs to.
     *
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
