<?php

namespace App\Models;

use Database\Factories\ItemOptionGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemOptionGroup extends Model
{
    /** @use HasFactory<ItemOptionGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'item_id',
        'name_local',
        'name_en',
        'min_select',
        'max_select',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_select' => 'integer',
            'max_select' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ItemOptionGroupOption::class, 'group_id')->orderBy('sort_order');
    }
}
