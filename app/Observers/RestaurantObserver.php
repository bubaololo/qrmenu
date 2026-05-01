<?php

namespace App\Observers;

use App\Enums\RestaurantUserRole;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;

class RestaurantObserver
{
    /**
     * Auto-add the creator as owner in restaurant_users when a restaurant is created.
     */
    public function created(Restaurant $restaurant): void
    {
        $userId = $restaurant->created_by_user_id;

        if ($userId) {
            $restaurant->restaurantUsers()->create([
                'user_id' => $userId,
                'role' => RestaurantUserRole::Owner,
            ]);
        }
    }

    /**
     * Bulk-delete polymorphic translations of all descendant menus/sections/items/groups/options
     * before FK CASCADE wipes their rows. The trait HasTranslations handles the restaurant's own
     * translations.
     */
    public function deleting(Restaurant $restaurant): void
    {
        $menuIds = DB::table('menus')->where('restaurant_id', $restaurant->id)->pluck('id');
        $sectionIds = $menuIds->isEmpty()
            ? collect()
            : DB::table('menu_sections')->whereIn('menu_id', $menuIds)->pluck('id');
        $itemIds = $sectionIds->isEmpty()
            ? collect()
            : DB::table('menu_items')->whereIn('section_id', $sectionIds)->pluck('id');
        $groupIds = $sectionIds->isEmpty()
            ? collect()
            : DB::table('menu_option_groups')->whereIn('section_id', $sectionIds)->pluck('id');
        $optionIds = $groupIds->isEmpty()
            ? collect()
            : DB::table('menu_option_group_options')->whereIn('group_id', $groupIds)->pluck('id');
        $zoneIds = DB::table('zones')->where('restaurant_id', $restaurant->id)->pluck('id');

        if ($sectionIds->isEmpty() && $itemIds->isEmpty() && $groupIds->isEmpty()
            && $optionIds->isEmpty() && $zoneIds->isEmpty()) {
            return;
        }

        Translation::query()
            ->where(function ($q) use ($sectionIds, $itemIds, $groupIds, $optionIds, $zoneIds) {
                if ($sectionIds->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('translatable_type', MenuSection::class)
                        ->whereIn('translatable_id', $sectionIds));
                }
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
                if ($zoneIds->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('translatable_type', Zone::class)
                        ->whereIn('translatable_id', $zoneIds));
                }
            })
            ->delete();
    }
}
