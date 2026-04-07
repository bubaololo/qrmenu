<?php

namespace App\Models;

use App\Enums\PriceType;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected $fillable = [
        'section_id',
        'name_local',
        'name_en',
        'description_local',
        'description_en',
        'starred',
        'price_type',
        'price_value',
        'price_min',
        'price_max',
        'price_unit',
        'price_unit_en',
        'price_original_text',
        'image_bbox',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'starred' => 'boolean',
            'price_type' => PriceType::class,
            'price_value' => 'decimal:2',
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'image_bbox' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class, 'section_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ItemVariation::class, 'item_id')->orderBy('sort_order');
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(ItemOptionGroup::class, 'item_id')->orderBy('sort_order');
    }
}
