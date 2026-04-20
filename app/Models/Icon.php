<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Icon extends Model
{
    protected $fillable = ['name', 'svg'];

    public function sections(): HasMany
    {
        return $this->hasMany(MenuSection::class);
    }
}
