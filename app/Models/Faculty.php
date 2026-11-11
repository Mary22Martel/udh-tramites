<?php

namespace App\Models;

use App\Models\Concerns\HasMailAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    use HasMailAccount;

    protected $hidden = ['mail_password'];

    protected $fillable = ['code', 'name', 'dean_name', 'dean_email'];

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }
}
