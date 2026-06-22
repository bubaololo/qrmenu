<?php

namespace App\Actions;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Delete every translation of a menu in a single language.
 *
 * Only non-source languages can be removed. The source language holds the
 * `is_initial` rows — the source-of-truth every other language is translated
 * FROM — so it must be reassigned (see {@see ChangeMenuSourceLocaleAction})
 * before it can go. The restaurant's `primary_language` is also protected: it is
 * force-added back by {@see Menu::availableLocales()}, so deleting it would only
 * make it reappear empty.
 *
 * The wipe is a mass delete: it fires no Eloquent events, so the
 * TranslationObserver never re-dispatches translation jobs for the menu.
 */
class DeleteMenuLocaleAction
{
    public function __invoke(Menu $menu, string $locale): void
    {
        if ($locale === '' || $locale === 'mixed') {
            throw new HttpException(422, 'A valid language is required.');
        }

        if ($locale === $menu->source_locale) {
            throw new HttpException(422, "'{$locale}' is the original language. Make another language the original before removing it.");
        }

        $menu->loadMissing('restaurant');
        if ($locale === ($menu->restaurant?->primary_language)) {
            throw new HttpException(422, "'{$locale}' is the restaurant's primary language and can't be removed.");
        }

        $sectionIds = $menu->sections()->pluck('id');
        $itemIds = MenuItem::whereIn('section_id', $sectionIds)->pluck('id');
        $groupIds = ModifierGroup::where('menu_id', $menu->id)->pluck('id');
        $optionIds = ModifierOption::whereIn('group_id', $groupIds)->pluck('id');

        $scope = function (Builder $q) use ($sectionIds, $itemIds, $groupIds, $optionIds): void {
            $q->where(fn (Builder $q) => $q->where('translatable_type', MenuSection::class)->whereIn('translatable_id', $sectionIds))
                ->orWhere(fn (Builder $q) => $q->where('translatable_type', MenuItem::class)->whereIn('translatable_id', $itemIds))
                ->orWhere(fn (Builder $q) => $q->where('translatable_type', ModifierGroup::class)->whereIn('translatable_id', $groupIds))
                ->orWhere(fn (Builder $q) => $q->where('translatable_type', ModifierOption::class)->whereIn('translatable_id', $optionIds));
        };

        Translation::where($scope)->where('locale', $locale)->delete();

        // Drop the on-demand throttle so the language can be re-translated at once.
        Cache::forget("menu_translation:{$menu->id}:{$locale}");
    }
}
