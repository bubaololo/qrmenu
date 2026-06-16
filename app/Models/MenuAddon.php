<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\MenuAddonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An atomic, additive extra (e.g. "Extra cheese"). `price` is a DELTA added on
 * top of the dish price. Reused across items of the menu via menu_item_addon.
 */
class MenuAddon extends Model
{
    /** @use HasFactory<MenuAddonFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var array<int, string> */
    protected $appends = ['name'];

    protected $fillable = [
        'menu_id',
        'price',
        'sort_order',
    ];

    /** Pending translation value to be written after save */
    protected ?string $pendingName = null;

    protected static function booted(): void
    {
        static::saved(function (MenuAddon $addon) {
            $locale = $addon->menu?->source_locale;
            if ($addon->pendingName !== null && $locale !== null) {
                $addon->setTranslation('name', $locale, $addon->pendingName, true);
                $addon->pendingName = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function getNameAttribute(): ?string
    {
        return $this->localizedText('name');
    }

    public function setNameAttribute(?string $value): void
    {
        $this->pendingName = $value;
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_addon', 'addon_id', 'item_id');
    }
}
