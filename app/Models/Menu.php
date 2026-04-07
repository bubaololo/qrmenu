<?php

namespace App\Models;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'detected_date',
        'source_images_count',
        'is_active',
        'created_from_menu_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'detected_date' => 'date',
            'source_images_count' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(MenuSection::class)->orderBy('sort_order');
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'created_from_menu_id');
    }

    public function clones(): HasMany
    {
        return $this->hasMany(Menu::class, 'created_from_menu_id');
    }

    /**
     * Activate this menu and deactivate all others for the same restaurant.
     */
    public function activate(): void
    {
        static::where('restaurant_id', $this->restaurant_id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    /** @param  Builder<Menu>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
