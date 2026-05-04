<?php

namespace App\Models;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Matriphe\ISO639\ISO639;

class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'source_locale',
        'detected_date',
        'source_images_count',
        'created_from_menu_id',
    ];

    protected function casts(): array
    {
        return [
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
     * Return the list of locales available for this menu (always includes source_locale and primary_language).
     *
     * @return Collection<int, array{code: string, name: string, is_source: bool}>
     */
    public function availableLocales(): Collection
    {
        $this->loadMissing(['sections.items', 'restaurant']);

        $itemIds = $this->sections->flatMap->items->pluck('id');

        $locales = Translation::where('translatable_type', MenuItem::class)
            ->whereIn('translatable_id', $itemIds)
            ->distinct()
            ->pluck('locale')
            ->values();

        $sourceLocale = $this->source_locale;
        $primaryLang = $this->restaurant?->primary_language ?? 'en';

        foreach (array_filter([$sourceLocale, $primaryLang]) as $code) {
            if (! $locales->contains($code)) {
                $locales->push($code);
            }
        }

        $iso = new ISO639;

        return $locales->map(fn (string $code) => [
            'code' => $code,
            'name' => $iso->nativeByCode1($code, true) ?: strtoupper($code),
            'is_source' => $code === $sourceLocale,
        ])->sortBy('name')->values();
    }
}
