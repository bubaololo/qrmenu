<?php

namespace App\Models;

use Database\Factories\VariationOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariationOption extends Model
{
    /** @use HasFactory<VariationOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'variation_id',
        'name_local',
        'name_en',
        'price_adjust',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_adjust' => 'decimal:2',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ItemVariation::class, 'variation_id');
    }
}
