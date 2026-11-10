<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Evento del historial / línea de tiempo de una solicitud. */
class SubmissionEvent extends Model
{
    protected $fillable = [
        'submission_id', 'step_number', 'step_name', 'actor_id', 'actor_name', 'actor_role',
        'actor_area', 'action', 'comment', 'document_name',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
