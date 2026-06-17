<?php

namespace App\Actions;

use App\Enums\ModifierPricingMode;
use App\Enums\PriceType;
use App\Models\Icon;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Observers\MenuItemObserver;
use App\Support\MenuJson;
use Illuminate\Support\Facades\DB;

class SaveMenuAnalysisAction
{
    /** @var list<string> */
    private array $allowedIconNames = [];

    /**
     * Persist a decoded LLM menu structure as a new Menu version for the given restaurant.
     *
     * @param  array<string, mixed>  $menuData  Output of MenuJson::decodeMenuFromLlmText()
     */
    public function handle(array $menuData, int $restaurantId, int $sourceImagesCount): Menu
    {
        if (MenuJson::dishCount($menuData) === 0) {
            throw new \RuntimeException('Cannot save an empty menu: no items were parsed from the LLM response.');
        }

        return $this->createMenu($menuData, $restaurantId, $sourceImagesCount);
    }

    /**
     * Create a Menu from parsed chunk/analysis data without the non-empty check
     * that `handle()` enforces. Used by the chunked flow where the first chunk
     * may legitimately contain only restaurant metadata (cover page) and later
     * chunks add the items via `appendChunk`.
     *
     * @param  array<string, mixed>  $menuData
     */
    public function createMenu(array $menuData, int $restaurantId, int $sourceImagesCount): Menu
    {
        $this->allowedIconNames = Icon::query()->pluck('name')->all();

        return DB::transaction(fn (): Menu => MenuItemObserver::muted(fn (): Menu => Translation::withoutEvents(function () use ($menuData, $restaurantId, $sourceImagesCount): Menu {
            $restaurant = Restaurant::findOrFail($restaurantId);
            $this->fillRestaurantFromLlm($restaurant, $menuData);

            $version = $menuData['menu_version'] ?? [];

            // A menu has exactly one original language. The analyzer prompt now
            // returns a single concrete primary_language (never 'mixed'); we
            // resolve any legacy 'mixed'/empty to the restaurant's
            // primary_language so source_locale and the is_initial rows always
            // agree on one concrete locale.
            $sourceLocale = $this->resolveConcreteLocale(
                $menuData['restaurant']['primary_language'] ?? null,
                $restaurant,
            );

            // Each restaurant has a single menu. Re-running analysis replaces
            // the previous one (sections / items / option groups cascade out).
            Menu::where('restaurant_id', $restaurantId)->delete();

            $menu = Menu::create([
                'restaurant_id' => $restaurantId,
                'source_locale' => $sourceLocale,
                'source_images_count' => (int) ($version['source_images_count'] ?? $sourceImagesCount),
                'detected_date' => $version['detected_date'] ?? now()->toDateString(),
            ]);

            $this->createSectionsForMenu(
                $menu,
                MenuJson::sections($menuData),
                sortOrderStart: 0,
                imageOffset: 0,
                sourceLocale: $sourceLocale,
                globalAddons: $this->flattenAddons($menuData, 'global_addons', 'global_options'),
            );

            return $menu;
        })));
    }

    /**
     * Append one chunk's parsed menu data to an existing Menu (used by chunked analysis flow).
     *
     * Enriches restaurant / menu metadata only where values are currently missing, then
     * appends new sections with continued sort_order and remapped image_bbox.image_index.
     *
     * @param  array<string, mixed>  $chunkData
     */
    public function appendChunk(Menu $menu, array $chunkData, int $imageOffset): void
    {
        $this->allowedIconNames = Icon::query()->pluck('name')->all();

        DB::transaction(fn () => MenuItemObserver::muted(fn () => Translation::withoutEvents(function () use ($menu, $chunkData, $imageOffset): void {
            $menu->loadMissing('restaurant');
            $this->enrichRestaurantIfEmpty($menu->restaurant, $chunkData);

            $sourceLocale = $menu->source_locale
                ?? $this->resolveConcreteLocale(
                    $chunkData['restaurant']['primary_language'] ?? null,
                    $menu->restaurant,
                );

            if ($menu->source_locale === null) {
                $menu->update(['source_locale' => $sourceLocale]);
            }

            $startSort = ((int) ($menu->sections()->max('sort_order') ?? -1)) + 1;

            $this->createSectionsForMenu(
                $menu,
                MenuJson::sections($chunkData),
                sortOrderStart: $startSort,
                imageOffset: $imageOffset,
                sourceLocale: $sourceLocale,
                globalAddons: $this->flattenAddons($chunkData, 'global_addons', 'global_options'),
            );
        })));
    }

    /**
     * Iterate a list of section payloads and persist each as a MenuSection on the given menu.
     * Extracted so both initial save (handle) and chunk append share the same section/item creation logic.
     *
     * @param  list<array<string, mixed>>  $sectionsData
     */
    private function createSectionsForMenu(
        Menu $menu,
        array $sectionsData,
        int $sortOrderStart,
        int $imageOffset,
        ?string $sourceLocale,
        array $globalAddons = [],
    ): void {
        // Collect per-item variation/add-on payloads across all sections, then
        // dedupe once at menu level so identical variations/add-ons are shared.
        /** @var array<int, array{item: MenuItem, variations: list<array<string, mixed>>, addons: list<array<string, mixed>>}> */
        $itemEntries = [];

        foreach ($sectionsData as $i => $sectionData) {
            $itemEntries = array_merge($itemEntries, $this->createSection(
                $menu,
                $sectionData,
                sortOrder: $sortOrderStart + $i,
                imageOffset: $imageOffset,
                sourceLocale: $sourceLocale,
            ));
        }

        // Menu-scoped add-ons apply to every item — fold them into each item's
        // add-on list so dedup creates ONE shared add-on attached to all items.
        if ($globalAddons !== []) {
            foreach ($itemEntries as &$entry) {
                $entry['addons'] = array_merge($entry['addons'], $globalAddons);
            }
            unset($entry);
        }

        $this->dedupeAttachVariationGroups($menu, $itemEntries, $sourceLocale);
        $this->dedupeAttachAddonGroups($menu, $itemEntries, $sourceLocale);
    }

    /**
     * Resolve a menu's single original language to a concrete ISO code. The
     * analyzer prompt returns one concrete primary_language; this guards the
     * legacy 'mixed'/empty case by falling back to the restaurant's
     * primary_language (then 'en'), so source_locale is never 'mixed'/null.
     */
    private function resolveConcreteLocale(mixed $llmPrimaryLanguage, Restaurant $restaurant): string
    {
        if (is_string($llmPrimaryLanguage) && $llmPrimaryLanguage !== '' && $llmPrimaryLanguage !== 'mixed') {
            return $llmPrimaryLanguage;
        }

        return $restaurant->primary_language ?: 'en';
    }

    /** @param  array<string, mixed>  $menuData */
    private function fillRestaurantFromLlm(Restaurant $restaurant, array $menuData): void
    {
        $r = $menuData['restaurant'] ?? null;
        if (! is_array($r)) {
            return;
        }

        $updates = array_filter([
            'name' => MenuJson::extractText($r['name'] ?? null),
            'address' => MenuJson::extractText($r['address'] ?? null),
            'city' => isset($r['city']) ? (string) $r['city'] : null,
            'country' => isset($r['country']) ? (string) $r['country'] : null,
            'phone' => isset($r['phone']) ? (string) $r['phone'] : null,
            'currency' => isset($r['currency']) ? (string) $r['currency'] : null,
            // 'mixed' is a valid LLM source-locale signal for a menu, but never a
            // sensible value for the restaurant's primary_language column — it
            // breaks the initial-translation fallback in createSection. Drop it.
            'primary_language' => isset($r['primary_language']) && $r['primary_language'] !== 'mixed'
                ? (string) $r['primary_language']
                : null,
            'opening_hours' => isset($r['opening_hours']) && is_array($r['opening_hours'])
                ? $r['opening_hours']
                : null,
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($updates)) {
            $restaurant->update($updates);
        }
    }

    /**
     * Fill restaurant fields that are currently empty from chunk data.
     * Does not overwrite existing values — subsequent chunks cannot clobber earlier ones.
     *
     * @param  array<string, mixed>  $chunkData
     */
    private function enrichRestaurantIfEmpty(Restaurant $restaurant, array $chunkData): void
    {
        $r = $chunkData['restaurant'] ?? null;
        if (! is_array($r)) {
            return;
        }

        $updates = [];
        foreach (['city', 'country', 'phone', 'currency', 'primary_language'] as $field) {
            $value = isset($r[$field]) ? (string) $r[$field] : '';
            // See fillRestaurantFromLlm: 'mixed' is a menu source-locale marker,
            // not a real language for restaurant.primary_language.
            if ($field === 'primary_language' && $value === 'mixed') {
                continue;
            }
            if ($value !== '' && empty($restaurant->{$field})) {
                $updates[$field] = $value;
            }
        }
        if (isset($r['opening_hours']) && is_array($r['opening_hours']) && $restaurant->opening_hours === null) {
            $updates['opening_hours'] = $r['opening_hours'];
        }

        $name = MenuJson::extractText($r['name'] ?? null);
        if ($name !== null && empty($restaurant->name)) {
            $updates['name'] = $name;
        }

        $address = MenuJson::extractText($r['address'] ?? null);
        if ($address !== null && empty($restaurant->address)) {
            $updates['address'] = $address;
        }

        if (! empty($updates)) {
            $restaurant->update($updates);
        }
    }

    /**
     * Persist one section and its items, returning the per-item variation/add-on
     * payloads so the caller can deduplicate and attach them at the menu level.
     *
     * @param  array<string, mixed>  $sectionData
     * @return array<int, array{item: MenuItem, variations: list<array<string, mixed>>, addons: list<array<string, mixed>>}>
     */
    private function createSection(Menu $menu, array $sectionData, int $sortOrder, int $imageOffset, ?string $sourceLocale): array
    {
        $iconName = $this->validateIconName($sectionData['category_icon'] ?? null);
        $section = $menu->sections()->create([
            'sort_order' => $sortOrder,
            'icon_id' => $iconName !== null ? Icon::where('name', $iconName)->value('id') : null,
        ]);

        // Initial translations are written under the menu's one concrete source
        // locale (already resolved upstream in createMenu/appendChunk).
        $locale = $sourceLocale;

        $name = MenuJson::extractText($sectionData['category_name'] ?? null);
        if ($name !== null && $locale !== null) {
            $section->setTranslation('name', $locale, $name, isInitial: true);
        }

        /** @var array<int, array{item: MenuItem, variations: list<array<string, mixed>>, addons: list<array<string, mixed>>}> */
        $itemEntries = [];

        // Section-scoped add-ons apply to every item in this section.
        $sectionAddons = $this->flattenAddons($sectionData, 'section_addons', 'section_options');

        foreach ($sectionData['items'] ?? [] as $itemIndex => $itemData) {
            $item = $this->createItem($section, $itemData, $itemIndex, $locale, $imageOffset);
            $itemEntries[] = [
                'item' => $item,
                'variations' => $itemData['variations'] ?? [],
                'addons' => array_merge(
                    $this->flattenAddons($itemData, 'addons', 'options'),
                    $sectionAddons,
                ),
            ];
        }

        return $itemEntries;
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function createItem(MenuSection $section, array $itemData, int $index, ?string $locale, int $imageOffset): MenuItem
    {
        $price = is_array($itemData['price'] ?? null) ? $itemData['price'] : [];

        $priceType = match (true) {
            isset($price['min']) && isset($price['max']) => PriceType::Range,
            isset($price['min']) && ! isset($price['max']) => PriceType::From,
            ! isset($price['value']) || $price['value'] === null => PriceType::Variable,
            default => PriceType::Fixed,
        };

        $item = $section->items()->create([
            'starred' => (bool) ($itemData['starred'] ?? false),
            'price_type' => $priceType,
            'price_value' => $price['value'] ?? null,
            'price_min' => $price['min'] ?? null,
            'price_max' => $price['max'] ?? null,
            'price_unit' => isset($price['unit']) && $price['unit'] !== '' ? (string) $price['unit'] : null,
            'price_original_text' => (string) ($price['original_text'] ?? ''),
            'image_bbox' => $this->cleanBbox($itemData['image_bbox'] ?? null, $imageOffset),
            'sort_order' => $index,
        ]);

        $name = MenuJson::extractText($itemData['name'] ?? null);
        if ($name !== null && $locale !== null) {
            $item->setTranslation('name', $locale, $name, isInitial: true);
        }

        $description = MenuJson::extractText($itemData['description'] ?? null);
        if ($description !== null && $locale !== null) {
            $item->setTranslation('description', $locale, $description, isInitial: true);
        }

        return $item;
    }

    /**
     * Dedupe pick-one variation axes across the menu's items and attach as
     * REPLACE modifier groups (single-select, required; option price ABSOLUTE).
     * Reuses replace-groups already on the menu (repeated saves/chunks).
     *
     * @param  array<int, array{item: MenuItem, variations: list<array<string, mixed>>, addons: list<array<string, mixed>>}>  $itemEntries
     */
    private function dedupeAttachVariationGroups(Menu $menu, array $itemEntries, ?string $locale): void
    {
        /** @var array<string, array{model: ?ModifierGroup, data: ?array<string, mixed>, itemIds: list<int>}> */
        $registry = [];

        foreach ($menu->modifierGroups()->where('pricing_mode', ModifierPricingMode::Replace->value)->with('options')->get() as $existing) {
            $key = $this->buildVariationKey([
                'name' => $existing->initialText('name'),
                'options' => $existing->options->map(fn (ModifierOption $o) => [
                    'name' => $o->initialText('name'),
                    'price' => $o->price,
                ])->all(),
            ]);
            $registry[$key] = ['model' => $existing, 'data' => null, 'itemIds' => []];
        }

        foreach ($itemEntries as $entry) {
            foreach ($entry['variations'] as $varData) {
                $key = $this->buildVariationKey($varData);
                if (! isset($registry[$key])) {
                    $registry[$key] = ['model' => null, 'data' => $varData, 'itemIds' => []];
                }
                $registry[$key]['itemIds'][] = $entry['item']->id;
            }
        }

        $sortOrder = ((int) ($menu->modifierGroups()->max('sort_order') ?? -1)) + 1;

        foreach ($registry as $entry) {
            if (empty($entry['itemIds'])) {
                continue;
            }
            $group = $entry['model'];
            if ($group === null) {
                $data = $entry['data'];
                $group = $menu->modifierGroups()->create([
                    'pricing_mode' => ModifierPricingMode::Replace,
                    'selection_type' => 'single',
                    'selection_min' => 1,
                    'selection_max' => 1,
                    'required' => true,
                    'sort_order' => $sortOrder++,
                ]);
                $name = MenuJson::extractText($data['name'] ?? $data['group_name'] ?? null);
                if ($name !== null && $locale !== null) {
                    $group->setTranslation('name', $locale, $name, isInitial: true);
                }
                foreach ($data['options'] ?? [] as $optIndex => $optData) {
                    $option = $group->options()->create([
                        'price' => $optData['price'] ?? $optData['price_adjust'] ?? null,
                        'is_default' => (bool) ($optData['is_default'] ?? false),
                        'sort_order' => $optIndex,
                    ]);
                    $optName = MenuJson::extractText($optData['name'] ?? null);
                    if ($optName !== null && $locale !== null) {
                        $option->setTranslation('name', $locale, $optName, isInitial: true);
                    }
                }
            }
            $group->items()->syncWithoutDetaching(array_unique($entry['itemIds']));
        }
    }

    /**
     * Dedupe atomic add-ons into ADD modifier groups (optional multi-select;
     * option price DELTA). Items that share the same add-on SET share one group
     * (mirrors the catalog migration), so per-item subsets are preserved.
     * Reuses add-groups already on the menu by option-set signature.
     *
     * @param  array<int, array{item: MenuItem, variations: list<array<string, mixed>>, addons: list<array<string, mixed>>}>  $itemEntries
     */
    private function dedupeAttachAddonGroups(Menu $menu, array $itemEntries, ?string $locale): void
    {
        /** @var array<string, ModifierGroup> */
        $existingBySignature = [];
        foreach ($menu->modifierGroups()->where('pricing_mode', ModifierPricingMode::Add->value)->with('options')->get() as $existing) {
            $signature = $this->buildAddonSetKey($existing->options->map(fn (ModifierOption $o) => [
                'name' => $o->initialText('name'),
                'price' => $o->price,
            ])->all());
            $existingBySignature[$signature] = $existing;
        }

        // Build each item's distinct add-on set, then group items by signature.
        /** @var array<string, array{options: list<array<string, mixed>>, itemIds: list<int>}> */
        $sets = [];
        foreach ($itemEntries as $entry) {
            $options = [];
            $seen = [];
            foreach ($entry['addons'] as $addonData) {
                $name = MenuJson::extractText($addonData['name'] ?? null);
                if ($name === null || trim($name) === '') {
                    continue;
                }
                $price = $this->lowEndPrice($addonData['price'] ?? $addonData['price_adjust'] ?? 0);
                $dedup = strtolower(trim($name)).':'.$price;
                if (isset($seen[$dedup])) {
                    continue;
                }
                $seen[$dedup] = true;
                $options[] = ['name' => $name, 'price' => $price];
            }
            if ($options === []) {
                continue;
            }
            $signature = $this->buildAddonSetKey($options);
            if (! isset($sets[$signature])) {
                $sets[$signature] = ['options' => $options, 'itemIds' => []];
            }
            $sets[$signature]['itemIds'][] = $entry['item']->id;
        }

        $sortOrder = ((int) ($menu->modifierGroups()->max('sort_order') ?? -1)) + 1;

        foreach ($sets as $signature => $set) {
            $group = $existingBySignature[$signature] ?? null;
            if ($group === null) {
                $group = $menu->modifierGroups()->create([
                    'pricing_mode' => ModifierPricingMode::Add,
                    'selection_type' => 'multi',
                    'selection_min' => 0,
                    'selection_max' => null,
                    'required' => false,
                    'sort_order' => $sortOrder++,
                ]);
                if ($locale !== null) {
                    $group->setTranslation('name', $locale, $this->addonGroupName($locale), isInitial: true);
                }
                foreach ($set['options'] as $optIndex => $optData) {
                    $option = $group->options()->create([
                        'price' => $optData['price'] ?? 0,
                        'is_default' => false,
                        'sort_order' => $optIndex,
                    ]);
                    if ($locale !== null) {
                        $option->setTranslation('name', $locale, (string) $optData['name'], isInitial: true);
                    }
                }
                $existingBySignature[$signature] = $group;
            }
            $group->items()->syncWithoutDetaching(array_unique($set['itemIds']));
        }
    }

    /**
     * Coerce a recognized add-on price to a number, taking the LOW end when the
     * model emits a range like "20000-40000" (size-dependent prices are then set
     * manually in the library). Already-numeric values pass through.
     */
    private function lowEndPrice(mixed $raw): float|int
    {
        if (is_numeric($raw)) {
            return $raw + 0;
        }
        if (is_string($raw) && preg_match('/\d[\d.,]*/', $raw, $matches) === 1) {
            return (float) str_replace(',', '', $matches[0]);
        }

        return 0;
    }

    /** A localized default name for a synthesized "Extras" add-on group. */
    private function addonGroupName(string $locale): string
    {
        return match ($locale) {
            'ru' => 'Добавки',
            'vi' => 'Thêm',
            'kk' => 'Қосымшалар',
            default => 'Extras',
        };
    }

    private function validateIconName(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return in_array($raw, $this->allowedIconNames, true) ? $raw : null;
    }

    private function cleanBbox(mixed $raw, int $imageOffset = 0): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $allowed = ['image_index', 'coords', 'confidence'];
        $clean = array_intersect_key($raw, array_flip($allowed));

        if ($imageOffset !== 0 && isset($clean['image_index']) && is_int($clean['image_index'])) {
            $clean['image_index'] += $imageOffset;
        }

        return $clean;
    }

    /**
     * Dedup key for a variation axis: name + sorted (option name:price).
     *
     * @param  array<string, mixed>  $data
     */
    private function buildVariationKey(array $data): string
    {
        $name = strtolower(trim((string) (MenuJson::extractText($data['name'] ?? $data['group_name'] ?? '') ?? '')));
        $options = collect($data['options'] ?? [])
            ->map(fn ($o) => strtolower(trim((string) (MenuJson::extractText($o['name'] ?? '') ?? '')))
                .':'.($o['price'] ?? $o['price_adjust'] ?? 0))
            ->sort()
            ->implode('|');

        return "v:{$name}:{$options}";
    }

    /**
     * Signature for an add-on group: the sorted set of its (name:price) options.
     * Items sharing the same signature share one "Extras" group.
     *
     * @param  list<array<string, mixed>>  $options
     */
    private function buildAddonSetKey(array $options): string
    {
        return collect($options)
            ->map(fn ($o) => strtolower(trim((string) (MenuJson::extractText($o['name'] ?? '') ?? '')))
                .':'.($o['price'] ?? $o['price_adjust'] ?? 0))
            ->sort()
            ->values()
            ->implode('|');
    }

    /**
     * Normalize add-ons from the new flat shape (`$flatKey`: [{name,price}]) or
     * the legacy grouped shape (`$groupKey`: [{options:[{name,price_adjust}]}]),
     * so the parser tolerates both prompt versions.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function flattenAddons(array $data, string $flatKey, string $groupKey): array
    {
        $flat = $data[$flatKey] ?? null;
        if (is_array($flat)) {
            return array_values(array_filter($flat, 'is_array'));
        }

        $out = [];
        foreach ($data[$groupKey] ?? [] as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group['options'] ?? [] as $opt) {
                $out[] = ['name' => $opt['name'] ?? null, 'price' => $opt['price'] ?? $opt['price_adjust'] ?? 0];
            }
        }

        return $out;
    }
}
