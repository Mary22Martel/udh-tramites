<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    public const LEVELS = [
        'PREGRADO' => 'Pregrado',
        'MAESTRIA' => 'Maestría',
        'DOCTORADO' => 'Doctorado',
        'SEGUNDA_ESPECIALIDAD' => 'Segunda especialización',
    ];

    protected $fillable = ['faculty_id', 'code', 'name', 'level', 'modalities', 'aliases', 'director_name', 'director_email'];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'ESTUDIANTE');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
