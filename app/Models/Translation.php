<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Translation extends Model
{
    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'locale',
        'field_id',
        'value',
        'is_initial',
    ];

    protected function casts(): array
    {
        return [
            'is_initial' => 'bool',
        ];
    }

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function translationField(): BelongsTo
    {
        return $this->belongsTo(TranslationField::class, 'field_id');
    }

    protected function field(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->translationField->name,
        );
    }
}
