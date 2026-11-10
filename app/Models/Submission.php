<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Solicitud (expediente) de un trámite presentada por un estudiante. */
class Submission extends Model
{
    public const STATUSES = [
        'EN_PROCESO' => 'En proceso',
        'OBSERVADO' => 'Observado',
        'FINALIZADO' => 'Finalizado',
        'RECHAZADO' => 'Rechazado',
    ];

    protected $fillable = [
        'code', 'procedure_id', 'procedure_code', 'procedure_name', 'procedure_category', 'cost',
        'student_id', 'faculty_id', 'program_id', 'description', 'status', 'current_step_index',
        'current_area', 'channel_id', 'payment_status', 'payment_method', 'payment_operation',
        'payment_amount', 'payment_date', 'observation', 'final_document_code', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'float',
            'payment_amount' => 'float',
            'payment_date' => 'date:Y-m-d',
            'observation' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AreaChannel::class, 'channel_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SubmissionDocument::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubmissionEvent::class)->orderBy('id');
    }
}
