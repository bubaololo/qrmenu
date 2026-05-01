<?php

namespace App\Observers;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;

class MenuObserver
{
    /**
     * Menu has no own translations (model does not use HasTranslations).
     * Pre-delete polymorphic translations of all descendant sections/items/groups/options
     * before FK CASCADE wipes their rows.
     */
    public function deleting(Menu $menu): void
    {
        $sectionIds = DB::table('menu_sections')->where('menu_id', $menu->id)->pluck('id');
        if ($sectionIds->isEmpty()) {
            return;
        }

        $itemIds = DB::table('menu_items')->whereIn('section_id', $sectionIds)->pluck('id');
        $groupIds = DB::table('menu_option_groups')->whereIn('section_id', $sectionIds)->pluck('id');
        $optionIds = $groupIds->isEmpty()
            ? collect()
            : DB::table('menu_option_group_options')->whereIn('group_id', $groupIds)->pluck('id');

        Translation::query()
            ->where(function ($q) use ($sectionIds, $itemIds, $groupIds, $optionIds) {
                $q->where(fn ($w) => $w->where('translatable_type', MenuSection::class)
                    ->whereIn('translatable_id', $sectionIds));
                if ($itemIds->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('translatable_type', MenuItem::class)
                        ->whereIn('translatable_id', $itemIds));
                }
                if ($groupIds->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('translatable_type', MenuOptionGroup::class)
                        ->whereIn('translatable_id', $groupIds));
                }
                if ($optionIds->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('translatable_type', MenuOptionGroupOption::class)
                        ->whereIn('translatable_id', $optionIds));
                }
            })
            ->delete();
    }
}
