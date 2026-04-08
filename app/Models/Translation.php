<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Translation extends Model
{
    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'locale_id',
        'field',
        'value',
        'is_initial',
    ];

    protected function casts(): array
    {
        return [
            'is_initial' => 'bool',
        ];
    }

    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class);
    }

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
