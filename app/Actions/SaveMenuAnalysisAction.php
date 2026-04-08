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
            $sourceLocale = $menuData['restaurant']['primary_language'] ?? null;

            $menu = Menu::create([
                'restaurant_id' => $restaurantId,
                'source_locale' => is_string($sourceLocale) && $sourceLocale !== '' ? $sourceLocale : null,
                'source_images_count' => (int) ($version['source_images_count'] ?? $sourceImagesCount),
                'is_active' => false,
                'detected_date' => $version['detected_date'] ?? now()->toDateString(),
            ]);

            foreach (MenuJson::sections($menuData) as $sectionIndex => $sectionData) {
                $this->createSection($menu, $sectionData, $sectionIndex, $sourceLocale);
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
     * @param  array<string, mixed>  $sectionData
     */
    private function createSection(Menu $menu, array $sectionData, int $index, ?string $sourceLocale): void
    {
        $section = $menu->sections()->create([
            'sort_order' => $sectionData['sort_order'] ?? $index,
        ]);

        $locale = $sourceLocale ?? 'und';
        $name = MenuJson::extractText($sectionData['category_name'] ?? null);
        if ($name !== null) {
            $section->setTranslation('name', $locale, $name, isInitial: true);
        }

        foreach ($sectionData['items'] ?? [] as $itemIndex => $itemData) {
            $this->createItem($section, $itemData, $itemIndex, $locale);
        }
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function createItem(MenuSection $section, array $itemData, int $index, string $locale): void
    {
        $price = is_array($itemData['price'] ?? null) ? $itemData['price'] : [];

        // Infer price type from presence of fields rather than LLM-provided type string
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
            'image_bbox' => is_array($itemData['image_bbox'] ?? null) ? $itemData['image_bbox'] : null,
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

        foreach ($itemData['variations'] ?? [] as $varIndex => $varData) {
            $this->createVariation($item, $varData, $varIndex, $locale);
        }

        // LLM may return option groups under "options" key
        $optionGroups = $itemData['option_groups'] ?? $itemData['options'] ?? [];
        foreach ($optionGroups as $groupIndex => $groupData) {
            if (! isset($groupData['group_name']) && ! isset($groupData['name'])) {
                continue;
            }
            $this->createOptionGroup($item, $groupData, $groupIndex, $locale);
        }
    }

    /**
     * @param  array<string, mixed>  $varData
     */
    private function createVariation(MenuItem $item, array $varData, int $index, string $locale): void
    {
        $variation = \App\Models\ItemVariation::create([
            'item_id' => $item->id,
            'type' => isset($varData['type']) && is_string($varData['type']) ? $varData['type'] : null,
            'required' => (bool) ($varData['required'] ?? false),
            'allow_multiple' => (bool) ($varData['allow_multiple'] ?? false),
            'sort_order' => $index,
        ]);

        $name = MenuJson::extractText($varData['name'] ?? null);
        if ($name !== null) {
            $variation->setTranslation('name', $locale, $name, isInitial: true);
        }

        foreach ($varData['options'] ?? [] as $optIndex => $optData) {
            $option = $variation->options()->create([
                'price_adjust' => $optData['price_adjust'] ?? 0,
                'is_default' => (bool) ($optData['is_default'] ?? false),
                'sort_order' => $optIndex,
            ]);
            $optName = MenuJson::extractText($optData['name'] ?? null);
            if ($optName !== null) {
                $option->setTranslation('name', $locale, $optName, isInitial: true);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $groupData
     */
    private function createOptionGroup(MenuItem $item, array $groupData, int $index, string $locale): void
    {
        $group = \App\Models\ItemOptionGroup::create([
            'item_id' => $item->id,
            'min_select' => (int) ($groupData['min_select'] ?? 0),
            'max_select' => isset($groupData['max_select']) ? (int) $groupData['max_select'] : null,
            'sort_order' => $index,
        ]);

        $name = MenuJson::extractText($groupData['group_name'] ?? $groupData['name'] ?? null);
        if ($name !== null) {
            $group->setTranslation('name', $locale, $name, isInitial: true);
        }

        foreach ($groupData['options'] ?? [] as $optIndex => $optData) {
            $option = $group->options()->create([
                'price_adjust' => $optData['price_adjust'] ?? 0,
                'sort_order' => $optIndex,
            ]);
            $optName = MenuJson::extractText($optData['name'] ?? null);
            if ($optName !== null) {
                $option->setTranslation('name', $locale, $optName, isInitial: true);
            }
        }
    }
}
