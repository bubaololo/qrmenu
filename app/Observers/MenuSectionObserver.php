<?php

namespace App\Observers;

use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;

class MenuSectionObserver
{
    /**
     * The section's own translations are handled by the HasTranslations trait.
     * Pre-delete polymorphic translations of descendant items/groups/options
     * before FK CASCADE wipes their rows.
     */
    public function deleting(MenuSection $section): void
    {
        $itemIds = DB::table('menu_items')->where('section_id', $section->id)->pluck('id');
        $groupIds = DB::table('menu_option_groups')->where('section_id', $section->id)->pluck('id');
        $optionIds = $groupIds->isEmpty()
            ? collect()
            : DB::table('menu_option_group_options')->whereIn('group_id', $groupIds)->pluck('id');

        if ($itemIds->isEmpty() && $groupIds->isEmpty() && $optionIds->isEmpty()) {
            return;
        }

        Translation::query()
            ->where(function ($q) use ($itemIds, $groupIds, $optionIds) {
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
