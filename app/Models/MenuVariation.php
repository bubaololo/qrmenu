<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\MenuVariationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pick-exactly-one axis for a dish (e.g. Size). Each option's price is the
 * ABSOLUTE price for that choice (replaces the dish price). Shared across items
 * of the menu via the menu_item_variation pivot.
 */
class MenuVariation extends Model
{
    /** @use HasFactory<MenuVariationFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var array<int, string> */
    protected $appends = ['name'];

    protected $fillable = [
        'menu_id',
        'sort_order',
    ];

    /** Pending translation value to be written after save */
    protected ?string $pendingName = null;

    protected static function booted(): void
    {
        static::saved(function (MenuVariation $variation) {
            $locale = $variation->menu?->source_locale;
            if ($variation->pendingName !== null && $locale !== null) {
                $variation->setTranslation('name', $locale, $variation->pendingName, true);
                $variation->pendingName = null;
            }
        });

        // Options are removed by FK cascade (no Eloquent events fire for them),
        // so their polymorphic translations would orphan. Clear them up-front
        // when a variation is deleted directly (e.g. via the API destroy).
        static::deleting(function (MenuVariation $variation) {
            $optionIds = $variation->options()->pluck('id');
            if ($optionIds->isNotEmpty()) {
                Translation::query()
                    ->where('translatable_type', MenuVariationOption::class)
                    ->whereIn('translatable_id', $optionIds)
                    ->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
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

    public function options(): HasMany
    {
        return $this->hasMany(MenuVariationOption::class, 'variation_id')->orderBy('sort_order');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_variation', 'variation_id', 'item_id');
    }
}
