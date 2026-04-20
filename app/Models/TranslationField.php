<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranslationField extends Model
{
    protected $fillable = ['name'];

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class, 'field_id');
    }
}
