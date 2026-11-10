<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcedureRequirement extends Model
{
    protected $fillable = ['procedure_id', 'name', 'description', 'is_required', 'file_types', 'max_size_mb', 'sort'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'file_types' => 'array'];
    }
}
