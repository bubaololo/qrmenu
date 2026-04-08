<?php

namespace App\Actions;

use App\Models\ItemOptionGroup;
use App\Models\ItemVariation;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;

class CloneMenuAction
{
    /**
     * Deep-clone a menu version (sections → items → variations/options + option groups/options).
     * The clone is inactive and retains a reference to the source menu.
     */
    public function handle(Menu $source): Menu
    {
        return DB::transaction(function () use ($source): Menu {
            $clone = Menu::create([
                'restaurant_id' => $source->restaurant_id,
                'source_locale' => $source->source_locale,
                'detected_date' => $source->detected_date,
                'source_images_count' => $source->source_images_count,
                'is_active' => false,
                'created_from_menu_id' => $source->id,
            ]);

            foreach ($source->sections()->with(['items.variations.options', 'items.optionGroups.options'])->get() as $section) {
                $this->cloneSection($clone, $section);
            }

            return $clone;
        });
    }

    private function cloneSection(Menu $cloneMenu, MenuSection $section): void
    {
        $newSection = $cloneMenu->sections()->create([
            'sort_order' => $section->sort_order,
        ]);

        $this->copyTranslations($section, $newSection);

        foreach ($section->items as $item) {
            $this->cloneItem($newSection, $item);
        }
    }

    private function cloneItem(MenuSection $newSection, MenuItem $item): void
    {
        $newItem = $newSection->items()->create([
            'starred' => $item->starred,
            'price_type' => $item->price_type,
            'price_value' => $item->price_value,
            'price_min' => $item->price_min,
            'price_max' => $item->price_max,
            'price_unit' => $item->price_unit,
            'price_original_text' => $item->price_original_text,
            'image_bbox' => $item->image_bbox,
            'sort_order' => $item->sort_order,
        ]);

        $this->copyTranslations($item, $newItem);

        foreach ($item->variations as $variation) {
            $this->cloneVariation($newItem, $variation);
        }

        foreach ($item->optionGroups as $group) {
            $this->cloneOptionGroup($newItem, $group);
        }
    }

    private function cloneVariation(MenuItem $newItem, ItemVariation $variation): void
    {
        $newVariation = ItemVariation::create([
            'item_id' => $newItem->id,
            'type' => $variation->type,
            'required' => $variation->required,
            'allow_multiple' => $variation->allow_multiple,
            'sort_order' => $variation->sort_order,
        ]);

        $this->copyTranslations($variation, $newVariation);

        foreach ($variation->options as $option) {
            $newOption = $newVariation->options()->create([
                'price_adjust' => $option->price_adjust,
                'is_default' => $option->is_default,
                'sort_order' => $option->sort_order,
            ]);
            $this->copyTranslations($option, $newOption);
        }
    }

    private function cloneOptionGroup(MenuItem $newItem, ItemOptionGroup $group): void
    {
        $newGroup = ItemOptionGroup::create([
            'item_id' => $newItem->id,
            'min_select' => $group->min_select,
            'max_select' => $group->max_select,
            'sort_order' => $group->sort_order,
        ]);

        $this->copyTranslations($group, $newGroup);

        foreach ($group->options as $option) {
            $newOption = $newGroup->options()->create([
                'price_adjust' => $option->price_adjust,
                'sort_order' => $option->sort_order,
            ]);
            $this->copyTranslations($option, $newOption);
        }
    }

    /**
     * Copy all translations from source entity to target entity.
     */
    private function copyTranslations(object $source, object $target): void
    {
        $sourceType = get_class($source);
        $targetType = get_class($target);

        $translations = Translation::where('translatable_type', $sourceType)
            ->where('translatable_id', $source->id)
            ->get();

        foreach ($translations as $t) {
            Translation::create([
                'translatable_type' => $targetType,
                'translatable_id' => $target->id,
                'locale_id' => $t->locale_id,
                'field' => $t->field,
                'value' => $t->value,
                'is_initial' => $t->is_initial,
            ]);
        }
    }
}
