<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Ventanilla de atención: reparte el trabajo de un área por carreras. */
class AreaChannel extends Model
{
    protected $fillable = ['area_id', 'name', 'responsible_id'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'area_channel_program');
    }
}
