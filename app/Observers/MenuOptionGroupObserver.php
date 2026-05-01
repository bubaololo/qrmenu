<?php

namespace App\Observers;

use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;

class MenuOptionGroupObserver
{
    /**
     * The group's own translations are handled by the HasTranslations trait.
     * Pre-delete polymorphic translations of descendant options before FK CASCADE
     * wipes their rows.
     */
    public function deleting(MenuOptionGroup $group): void
    {
        $optionIds = DB::table('menu_option_group_options')
            ->where('group_id', $group->id)
            ->pluck('id');

        if ($optionIds->isEmpty()) {
            return;
        }

        Translation::query()
            ->where('translatable_type', MenuOptionGroupOption::class)
            ->whereIn('translatable_id', $optionIds)
            ->delete();
    }
}
