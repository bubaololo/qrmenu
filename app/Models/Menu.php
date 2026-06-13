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
     * The concrete, editable locale that holds (or should hold) this menu's
     * initial (source-of-truth) translations.
     *
     * Differs from source_locale only for mixed-language menus: source_locale
     * is then the sentinel 'mixed', which is never a real translation row.
     * For those menus SaveMenuAnalysisAction::createSection stores the captured
     * OCR text under the restaurant's primary_language, so that is where the
     * is_initial=true rows actually live and where source edits must go.
     *
     * Returns null only when there is no usable locale at all (no source and
     * no primary_language) — callers treat that as "contract undefined".
     */
    public function initialLocale(): ?string
    {
        if ($this->source_locale !== null && $this->source_locale !== 'mixed') {
            return $this->source_locale;
        }

        return $this->restaurant?->primary_language;
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

        // 'mixed' is a menu-level attribute, not a locale anyone can translate
        // into. Drop it from the picker so the UI never offers it.
        $locales = $locales->reject(fn (string $code) => $code === 'mixed')->values();

        // The "source" badge marks the editable origin locale, not the raw
        // source_locale sentinel — for mixed menus that is primary_language.
        $initialLocale = $this->initialLocale();

        $iso = new ISO639;

        return $locales->map(fn (string $code) => [
            'code' => $code,
            'name' => $iso->nativeByCode1($code, true) ?: strtoupper($code),
            'is_source' => $code === $initialLocale,
        ])->sortBy('name')->values();
    }
}
