<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\ItemVariationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemVariation extends Model
{
    /** @use HasFactory<ItemVariationFactory> */
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'item_id',
        'type',
        'required',
        'allow_multiple',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'allow_multiple' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(VariationOption::class, 'variation_id')->orderBy('sort_order');
    }
}
