<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Locale extends Model
{
    protected $fillable = [
        'code',
        'name',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }
}
