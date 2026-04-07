<?php

namespace App\Actions;

use App\Enums\PriceType;
use App\Models\ItemOptionGroup;
use App\Models\ItemVariation;
use App\Models\Menu;
use App\Models\MenuItem;
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

        return DB::transaction(function () use ($menuData, $restaurantId, $sourceImagesCount): Menu {
            $restaurant = Restaurant::findOrFail($restaurantId);
            $this->fillRestaurantFromLlm($restaurant, $menuData);

            $version = $menuData['menu_version'] ?? [];
            $menu = Menu::create([
                'restaurant_id' => $restaurantId,
                'source_images_count' => (int) ($version['source_images_count'] ?? $sourceImagesCount),
                'is_active' => false,
                'detected_date' => $version['detected_date'] ?? now()->toDateString(),
            ]);

            foreach (MenuJson::sections($menuData) as $sectionIndex => $sectionData) {
                $this->createSection($menu, $sectionData, $sectionIndex);
            }

            return $menu;
        });
    }

    /** @param  array<string, mixed>  $menuData */
    private function fillRestaurantFromLlm(Restaurant $restaurant, array $menuData): void
    {
        $r = $menuData['restaurant'] ?? null;
        if (! is_array($r)) {
            return;
        }

        $namePair = MenuJson::bilingualPair($r['name'] ?? null);
        $addrPair = MenuJson::bilingualPair($r['address'] ?? null);

        $updates = array_filter([
            'name_local' => $namePair['secondary'] ?? ($namePair['primary'] ?: null),
            'name_en' => $namePair['secondary'] !== null ? $namePair['primary'] : null,
            'address_local' => $addrPair['secondary'] ?? ($addrPair['primary'] ?: null),
            'address_en' => $addrPair['secondary'] !== null ? $addrPair['primary'] : null,
            'district' => $r['district'] ?? null,
            'city' => $r['city'] ?? null,
            'province' => $r['province'] ?? null,
            'country' => $r['country'] ?? null,
            'phone' => $r['phone'] ?? null,
            'phone2' => $r['phone2'] ?? null,
            'currency' => $r['currency'] ?? null,
            'primary_language' => $r['primary_language'] ?? null,
            'opening_hours' => isset($r['opening_hours']) && is_array($r['opening_hours'])
                ? $r['opening_hours']
                : null,
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($updates)) {
            $restaurant->update($updates);
        }
    }

    /** @param  array<string, mixed>  $sectionData */
    private function createSection(Menu $menu, array $sectionData, int $index): void
    {
        $catPair = MenuJson::bilingualPair($sectionData['category_name'] ?? null);

        $section = $menu->sections()->create([
            'name_local' => $catPair['secondary'] ?? ($catPair['primary'] ?: 'Section '.($index + 1)),
            'name_en' => $catPair['secondary'] !== null ? $catPair['primary'] : null,
            'sort_order' => $sectionData['sort_order'] ?? $index,
        ]);

        foreach ($sectionData['items'] ?? [] as $itemIndex => $itemData) {
            $this->createItem($section, $itemData, $itemIndex);
        }
    }

    /** @param  array<string, mixed>  $itemData */
    private function createItem(MenuSection $section, array $itemData, int $index): void
    {
        $namePair = MenuJson::bilingualPair($itemData['name'] ?? null);
        $descPair = MenuJson::bilingualPair($itemData['description'] ?? null);
        $price = is_array($itemData['price'] ?? null) ? $itemData['price'] : [];

        $priceType = match ($price['type'] ?? 'fixed') {
            'range' => PriceType::Range,
            'from' => PriceType::From,
            'variable' => PriceType::Variable,
            default => PriceType::Fixed,
        };

        $item = $section->items()->create([
            'name_local' => $namePair['secondary'] ?? ($namePair['primary'] ?: '—'),
            'name_en' => $namePair['secondary'] !== null ? $namePair['primary'] : null,
            'description_local' => $descPair['secondary'] ?? ($descPair['primary'] ?: null),
            'description_en' => $descPair['secondary'] !== null ? $descPair['primary'] : null,
            'starred' => (bool) ($itemData['starred'] ?? false),
            'price_type' => $priceType,
            'price_value' => $price['value'] ?? null,
            'price_min' => $price['min'] ?? null,
            'price_max' => $price['max'] ?? null,
            'price_unit' => $price['unit'] ?? null,
            'price_unit_en' => $price['unit_en'] ?? null,
            'price_original_text' => (string) ($price['original_text'] ?? ''),
            'image_bbox' => is_array($itemData['image_bbox'] ?? null) ? $itemData['image_bbox'] : null,
            'sort_order' => $index,
        ]);

        foreach ($itemData['variations'] ?? [] as $varIndex => $varData) {
            $this->createVariation($item, $varData, $varIndex);
        }

        // LLM may return option groups under "options" key
        $optionGroups = $itemData['option_groups'] ?? $itemData['options'] ?? [];
        foreach ($optionGroups as $groupIndex => $groupData) {
            // Skip flat option arrays (not group objects)
            if (! isset($groupData['group_name']) && ! isset($groupData['name'])) {
                continue;
            }
            $this->createOptionGroup($item, $groupData, $groupIndex);
        }
    }

    /** @param  array<string, mixed>  $varData */
    private function createVariation(MenuItem $item, array $varData, int $index): void
    {
        $namePair = MenuJson::bilingualPair($varData['name'] ?? null);

        $variation = ItemVariation::create([
            'item_id' => $item->id,
            'type' => $varData['type'] ?? 'portion',
            'name_local' => $namePair['secondary'] ?? ($namePair['primary'] ?: '—'),
            'name_en' => $namePair['secondary'] !== null ? $namePair['primary'] : null,
            'required' => (bool) ($varData['required'] ?? false),
            'allow_multiple' => (bool) ($varData['allow_multiple'] ?? false),
            'sort_order' => $index,
        ]);

        foreach ($varData['options'] ?? [] as $optIndex => $optData) {
            $optPair = MenuJson::bilingualPair($optData['name'] ?? null);
            $variation->options()->create([
                'name_local' => $optPair['secondary'] ?? ($optPair['primary'] ?: '—'),
                'name_en' => $optPair['secondary'] !== null ? $optPair['primary'] : null,
                'price_adjust' => $optData['price_adjust'] ?? 0,
                'is_default' => (bool) ($optData['is_default'] ?? false),
                'sort_order' => $optIndex,
            ]);
        }
    }

    /** @param  array<string, mixed>  $groupData */
    private function createOptionGroup(MenuItem $item, array $groupData, int $index): void
    {
        $namePair = MenuJson::bilingualPair($groupData['group_name'] ?? $groupData['name'] ?? null);

        $group = ItemOptionGroup::create([
            'item_id' => $item->id,
            'name_local' => $namePair['secondary'] ?? ($namePair['primary'] ?: '—'),
            'name_en' => $namePair['secondary'] !== null ? $namePair['primary'] : null,
            'min_select' => (int) ($groupData['min_select'] ?? 0),
            'max_select' => isset($groupData['max_select']) ? (int) $groupData['max_select'] : null,
            'sort_order' => $index,
        ]);

        foreach ($groupData['options'] ?? [] as $optIndex => $optData) {
            $optPair = MenuJson::bilingualPair($optData['name'] ?? null);
            $group->options()->create([
                'name_local' => $optPair['secondary'] ?? ($optPair['primary'] ?: '—'),
                'name_en' => $optPair['secondary'] !== null ? $optPair['primary'] : null,
                'price_adjust' => $optData['price_adjust'] ?? 0,
                'sort_order' => $optIndex,
            ]);
        }
    }
}
