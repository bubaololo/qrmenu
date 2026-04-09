<?php

namespace App\Actions;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuSection;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;

class CloneMenuAction
{
    /**
     * Deep-clone a menu version (sections → optionGroups + items with pivot).
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

            $sections = $source->sections()->with([
                'items',
                'optionGroups.options',
                'optionGroups.items',
            ])->get();

            foreach ($sections as $section) {
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

        // Map old item IDs to new items
        /** @var array<int, MenuItem> */
        $itemMap = [];
        foreach ($section->items as $item) {
            $newItem = $this->cloneItem($newSection, $item);
            $itemMap[$item->id] = $newItem;
        }

        // Clone section-level groups and re-attach via pivot using mapped item IDs
        foreach ($section->optionGroups as $group) {
            $newGroup = MenuOptionGroup::create([
                'section_id' => $newSection->id,
                'type' => $group->type,
                'is_variation' => $group->is_variation,
                'required' => $group->required,
                'allow_multiple' => $group->allow_multiple,
                'min_select' => $group->min_select,
                'max_select' => $group->max_select,
                'sort_order' => $group->sort_order,
            ]);

            $this->copyTranslations($group, $newGroup);

            foreach ($group->options as $option) {
                $newOption = $newGroup->options()->create([
                    'price_adjust' => $option->price_adjust,
                    'is_default' => $option->is_default,
                    'sort_order' => $option->sort_order,
                ]);
                $this->copyTranslations($option, $newOption);
            }

            // Re-attach to cloned items
            $newItemIds = $group->items
                ->pluck('id')
                ->map(fn (int $oldId) => $itemMap[$oldId]->id ?? null)
                ->filter()
                ->values()
                ->all();

            if (! empty($newItemIds)) {
                $newGroup->items()->attach($newItemIds);
            }
        }
    }

    private function cloneItem(MenuSection $newSection, MenuItem $item): MenuItem
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

        return $newItem;
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
                'locale' => $t->locale,
                'field' => $t->field,
                'value' => $t->value,
                'is_initial' => $t->is_initial,
            ]);
        }
    }
}
