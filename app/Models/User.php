<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = [
        'ADMINISTRADOR' => 'Administrador del sistema',
        'ESTUDIANTE' => 'Estudiante',
        'TESORERIA' => 'Tesorería',
        'MATRICULA' => 'Matrícula y Registros',
        'PROGRAMA_ACADEMICO' => 'Dirección de Programa Académico',
        'FACULTAD' => 'Decanato de Facultad',
        'AREA_CENTRAL' => 'Área central',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'avatar', 'dni', 'phone',
        'student_code', 'faculty_id', 'program_id', 'current_semester', 'academic_state',
        'area_id', 'job_title', 'department', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['role_label'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'student_id');
    }

    public function getRoleLabelAttribute(): ?string
    {
        return $this->role ? (self::ROLES[$this->role] ?? $this->role) : null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMINISTRADOR';
    }

    public function isStudent(): bool
    {
        return $this->role === 'ESTUDIANTE';
    }

    public function isStaff(): bool
    {
        return ! $this->isStudent() && ! $this->isAdmin();
    }
}
