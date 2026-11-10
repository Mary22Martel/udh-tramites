<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procedure extends Model
{
    public const CATEGORIES = [
        'Constancias y Certificados',
        'Matrícula y Régimen de Estudios',
        'Evaluaciones y Notas',
        'Prácticas Pre Profesionales',
        'Investigación y Tesis',
        'Grados y Títulos',
        'Servicios y Extensión',
    ];

    protected $fillable = [
        'code', 'name', 'description', 'category', 'estimated_days', 'cost', 'requires_payment',
        'payment_concept', 'target_scope', 'is_active', 'version', 'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'float',
            'requires_payment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProcedureRequirement::class)->orderBy('sort');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProcedureStep::class)->orderBy('step_number');
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class);
    }

    public function faculties(): BelongsToMany
    {
        return $this->belongsToMany(Faculty::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Procedure::class, 'parent_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
