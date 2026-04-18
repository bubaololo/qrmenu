<?php

namespace App\Actions;

use App\Enums\PriceType;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Support\MenuJson;
use Illuminate\Support\Facades\DB;

class SaveMenuAnalysisAction
{
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
        return DB::transaction(function () use ($menuData, $restaurantId, $sourceImagesCount): Menu {
            $restaurant = Restaurant::findOrFail($restaurantId);
            $this->fillRestaurantFromLlm($restaurant, $menuData);

            $version = $menuData['menu_version'] ?? [];
            $sourceLocale = $menuData['restaurant']['primary_language'] ?? null;

            $menu = Menu::create([
                'restaurant_id' => $restaurantId,
                'source_locale' => is_string($sourceLocale) && $sourceLocale !== '' ? $sourceLocale : null,
                'source_images_count' => (int) ($version['source_images_count'] ?? $sourceImagesCount),
                'is_active' => false,
                'detected_date' => $version['detected_date'] ?? now()->toDateString(),
            ]);

            $menu->activate();

            $this->createSectionsForMenu(
                $menu,
                MenuJson::sections($menuData),
                sortOrderStart: 0,
                imageOffset: 0,
                sourceLocale: $sourceLocale,
            );

            return $menu;
        });
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
        DB::transaction(function () use ($menu, $chunkData, $imageOffset): void {
            $menu->loadMissing('restaurant');
            $this->enrichRestaurantIfEmpty($menu->restaurant, $chunkData);

            $sourceLocale = $menu->source_locale
                ?? (is_string($chunkData['restaurant']['primary_language'] ?? null) && $chunkData['restaurant']['primary_language'] !== ''
                    ? (string) $chunkData['restaurant']['primary_language']
                    : null);

            if ($menu->source_locale === null && $sourceLocale !== null) {
                $menu->update(['source_locale' => $sourceLocale]);
            }

            $startSort = ((int) ($menu->sections()->max('sort_order') ?? -1)) + 1;

            $this->createSectionsForMenu(
                $menu,
                MenuJson::sections($chunkData),
                sortOrderStart: $startSort,
                imageOffset: $imageOffset,
                sourceLocale: $sourceLocale,
            );
        });
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
    ): void {
        foreach ($sectionsData as $i => $sectionData) {
            $this->createSection(
                $menu,
                $sectionData,
                sortOrder: $sortOrderStart + $i,
                imageOffset: $imageOffset,
                sourceLocale: $sourceLocale,
            );
        }
    }

    /** @param  array<string, mixed>  $menuData */
    private function fillRestaurantFromLlm(Restaurant $restaurant, array $menuData): void
    {
        $r = $menuData['restaurant'] ?? null;
        if (! is_array($r)) {
            return;
        }

        $updates = array_filter([
            'city' => isset($r['city']) ? (string) $r['city'] : null,
            'country' => isset($r['country']) ? (string) $r['country'] : null,
            'phone' => isset($r['phone']) ? (string) $r['phone'] : null,
            'currency' => isset($r['currency']) ? (string) $r['currency'] : null,
            'primary_language' => isset($r['primary_language']) ? (string) $r['primary_language'] : null,
            'opening_hours' => isset($r['opening_hours']) && is_array($r['opening_hours'])
                ? $r['opening_hours']
                : null,
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($updates)) {
            $restaurant->update($updates);
        }

        $locale = is_string($r['primary_language'] ?? null) && ($r['primary_language'] ?? '') !== ''
            ? (string) $r['primary_language']
            : 'und';

        $name = MenuJson::extractText($r['name'] ?? null);
        if ($name !== null) {
            $restaurant->setTranslation('name', $locale, $name, isInitial: true);
        }

        $address = MenuJson::extractText($r['address'] ?? null);
        if ($address !== null) {
            $restaurant->setTranslation('address', $locale, $address, isInitial: true);
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
            if ($value !== '' && empty($restaurant->{$field})) {
                $updates[$field] = $value;
            }
        }
        if (isset($r['opening_hours']) && is_array($r['opening_hours']) && $restaurant->opening_hours === null) {
            $updates['opening_hours'] = $r['opening_hours'];
        }
        if (! empty($updates)) {
            $restaurant->update($updates);
        }

        $locale = is_string($r['primary_language'] ?? null) && $r['primary_language'] !== ''
            ? (string) $r['primary_language']
            : ($restaurant->primary_language ?? 'und');

        $name = MenuJson::extractText($r['name'] ?? null);
        if ($name !== null && $restaurant->initialText('name') === null) {
            $restaurant->setTranslation('name', $locale, $name, isInitial: true);
        }

        $address = MenuJson::extractText($r['address'] ?? null);
        if ($address !== null && $restaurant->initialText('address') === null) {
            $restaurant->setTranslation('address', $locale, $address, isInitial: true);
        }
    }

    /**
     * @param  array<string, mixed>  $sectionData
     */
    private function createSection(Menu $menu, array $sectionData, int $sortOrder, int $imageOffset, ?string $sourceLocale): void
    {
        $section = $menu->sections()->create([
            'sort_order' => $sortOrder,
            'category_icon' => $this->validateIcon($sectionData['category_icon'] ?? null),
        ]);

        $locale = $sourceLocale ?? 'und';
        $name = MenuJson::extractText($sectionData['category_name'] ?? null);
        if ($name !== null) {
            $section->setTranslation('name', $locale, $name, isInitial: true);
        }

        // Create items first, then deduplicate and attach groups
        /** @var array<int, array{item: MenuItem, variations: list<array<string, mixed>>, options: list<array<string, mixed>>}> */
        $itemEntries = [];

        foreach ($sectionData['items'] ?? [] as $itemIndex => $itemData) {
            $item = $this->createItem($section, $itemData, $itemIndex, $locale, $imageOffset);
            $itemEntries[] = [
                'item' => $item,
                'variations' => $itemData['variations'] ?? [],
                'options' => $itemData['option_groups'] ?? $itemData['options'] ?? [],
            ];
        }

        $this->deduplicateAndAttachGroups($section, $itemEntries, $locale);
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function createItem(MenuSection $section, array $itemData, int $index, string $locale, int $imageOffset): MenuItem
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
        if ($name !== null) {
            $item->setTranslation('name', $locale, $name, isInitial: true);
        }

        $description = MenuJson::extractText($itemData['description'] ?? null);
        if ($description !== null) {
            $item->setTranslation('description', $locale, $description, isInitial: true);
        }

        return $item;
    }

    /**
     * Deduplicate variations and option groups across items in a section,
     * create unique groups at section level, and attach items via pivot.
     *
     * @param  array<int, array{item: MenuItem, variations: list<array<string, mixed>>, options: list<array<string, mixed>>}>  $itemEntries
     */
    private function deduplicateAndAttachGroups(MenuSection $section, array $itemEntries, string $locale): void
    {
        // registry: key => ['data' => groupData, 'isVariation' => bool, 'itemIds' => int[], 'sortOrder' => int]
        $registry = [];

        foreach ($itemEntries as $entry) {
            $item = $entry['item'];

            foreach ($entry['variations'] as $varIndex => $varData) {
                $key = $this->buildGroupKey($varData, true);
                if (! isset($registry[$key])) {
                    $registry[$key] = ['data' => $varData, 'isVariation' => true, 'itemIds' => [], 'sortOrder' => $varIndex];
                }
                $registry[$key]['itemIds'][] = $item->id;
            }

            foreach ($entry['options'] as $groupIndex => $groupData) {
                if (! isset($groupData['group_name']) && ! isset($groupData['name'])) {
                    continue;
                }
                $key = $this->buildGroupKey($groupData, false);
                if (! isset($registry[$key])) {
                    $registry[$key] = ['data' => $groupData, 'isVariation' => false, 'itemIds' => [], 'sortOrder' => $groupIndex];
                }
                $registry[$key]['itemIds'][] = $item->id;
            }
        }

        $sortOrder = 0;
        foreach ($registry as $entry) {
            $groupData = $entry['data'];
            $isVariation = $entry['isVariation'];

            $group = MenuOptionGroup::create([
                'section_id' => $section->id,
                'type' => isset($groupData['type']) && is_string($groupData['type']) ? $groupData['type'] : null,
                'is_variation' => $isVariation,
                'required' => (bool) ($groupData['required'] ?? false),
                'allow_multiple' => (bool) ($groupData['allow_multiple'] ?? false),
                'min_select' => (int) ($groupData['min_select'] ?? 0),
                'max_select' => isset($groupData['max_select']) ? (int) $groupData['max_select'] : null,
                'sort_order' => $sortOrder++,
            ]);

            $groupName = MenuJson::extractText($groupData['group_name'] ?? $groupData['name'] ?? null);
            if ($groupName !== null) {
                $group->setTranslation('name', $locale, $groupName, isInitial: true);
            }

            foreach ($groupData['options'] ?? [] as $optIndex => $optData) {
                $option = $group->options()->create([
                    'price_adjust' => $optData['price_adjust'] ?? 0,
                    'is_default' => (bool) ($optData['is_default'] ?? false),
                    'sort_order' => $optIndex,
                ]);
                $optName = MenuJson::extractText($optData['name'] ?? null);
                if ($optName !== null) {
                    $option->setTranslation('name', $locale, $optName, isInitial: true);
                }
            }

            $group->items()->attach(array_unique($entry['itemIds']));
        }
    }

    private function validateIcon(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return in_array($raw, config('food_icons.allowed', []), true) ? $raw : null;
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
     * Build a deduplication key for a variation/option group.
     *
     * @param  array<string, mixed>  $data
     */
    private function buildGroupKey(array $data, bool $isVariation): string
    {
        $name = strtolower(trim((string) (MenuJson::extractText($data['group_name'] ?? $data['name'] ?? '') ?? '')));
        $type = $isVariation ? 'v' : 'o';
        $options = collect($data['options'] ?? [])
            ->map(fn ($o) => strtolower(trim((string) (MenuJson::extractText($o['name'] ?? '') ?? '')))
                .':'.($o['price_adjust'] ?? 0))
            ->sort()
            ->implode('|');

        return "{$type}:{$name}:{$options}";
    }
}
