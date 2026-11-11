<?php

namespace App\Models;

use App\Models\Concerns\HasMailAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasMailAccount;

    protected $hidden = ['mail_password'];

    protected $fillable = ['code', 'name', 'description', 'email', 'responsible_id', 'is_system', 'has_channels'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'has_channels' => 'boolean'];
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(AreaChannel::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
