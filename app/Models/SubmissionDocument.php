<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionDocument extends Model
{
    protected $fillable = [
        'submission_id', 'name', 'type', 'category', 'path', 'mime', 'size_bytes',
        'uploaded_by', 'uploaded_by_name', 'official_number', 'step_number',
    ];

    protected $hidden = ['path'];

    protected $appends = ['has_file'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function getHasFileAttribute(): bool
    {
        return (bool) $this->path;
    }
}
