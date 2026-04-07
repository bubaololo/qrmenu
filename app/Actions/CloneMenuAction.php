<?php

namespace App\Actions;

use App\Models\ItemOptionGroup;
use App\Models\ItemVariation;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
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
            'name_local' => $section->name_local,
            'name_en' => $section->name_en,
            'sort_order' => $section->sort_order,
        ]);

        foreach ($section->items as $item) {
            $this->cloneItem($newSection, $item);
        }
    }

    private function cloneItem(MenuSection $newSection, MenuItem $item): void
    {
        $newItem = $newSection->items()->create([
            'name_local' => $item->name_local,
            'name_en' => $item->name_en,
            'description_local' => $item->description_local,
            'description_en' => $item->description_en,
            'starred' => $item->starred,
            'price_type' => $item->price_type,
            'price_value' => $item->price_value,
            'price_min' => $item->price_min,
            'price_max' => $item->price_max,
            'price_unit' => $item->price_unit,
            'price_unit_en' => $item->price_unit_en,
            'price_original_text' => $item->price_original_text,
            'image_bbox' => $item->image_bbox,
            'sort_order' => $item->sort_order,
        ]);

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
            'name_local' => $variation->name_local,
            'name_en' => $variation->name_en,
            'required' => $variation->required,
            'allow_multiple' => $variation->allow_multiple,
            'sort_order' => $variation->sort_order,
        ]);

        foreach ($variation->options as $option) {
            $newVariation->options()->create([
                'name_local' => $option->name_local,
                'name_en' => $option->name_en,
                'price_adjust' => $option->price_adjust,
                'is_default' => $option->is_default,
                'sort_order' => $option->sort_order,
            ]);
        }
    }

    private function cloneOptionGroup(MenuItem $newItem, ItemOptionGroup $group): void
    {
        $newGroup = ItemOptionGroup::create([
            'item_id' => $newItem->id,
            'name_local' => $group->name_local,
            'name_en' => $group->name_en,
            'min_select' => $group->min_select,
            'max_select' => $group->max_select,
            'sort_order' => $group->sort_order,
        ]);

        foreach ($group->options as $option) {
            $newGroup->options()->create([
                'name_local' => $option->name_local,
                'name_en' => $option->name_en,
                'price_adjust' => $option->price_adjust,
                'sort_order' => $option->sort_order,
            ]);
        }
    }
}
