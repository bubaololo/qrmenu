<?php

namespace App\Actions;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Change a menu's single original language.
 *
 * `is_initial` marks the source-of-truth row a field is translated FROM, so the
 * menu's original language and its is_initial rows must always agree. Switching
 * the language therefore re-points the is_initial flag across EVERY entity of the
 * menu — atomically, without losing data (the old source is demoted to a normal
 * translation) and without re-translating (direct DB updates fire no observer;
 * existing translations stay as the basis for future translations only).
 *
 * The target must already be fully translated (every source field has a row in
 * it); otherwise some field would be left with no source.
 */
class ChangeMenuSourceLocaleAction
{
    public function __invoke(Menu $menu, string $target): void
    {
        if ($target === $menu->source_locale) {
            return;
        }

        if ($target === '') {
            throw new HttpException(422, 'Target language is required.');
        }

        $sectionIds = $menu->sections()->pluck('id');
        $itemIds = MenuItem::whereIn('section_id', $sectionIds)->pluck('id');
        $groupIds = MenuOptionGroup::where('menu_id', $menu->id)->pluck('id');
        $optionIds = MenuOptionGroupOption::whereIn('group_id', $groupIds)->pluck('id');

        $scope = function (Builder $q) use ($sectionIds, $itemIds, $groupIds, $optionIds): void {
            $q->where(fn (Builder $q) => $q->where('translatable_type', MenuSection::class)->whereIn('translatable_id', $sectionIds))
                ->orWhere(fn (Builder $q) => $q->where('translatable_type', MenuItem::class)->whereIn('translatable_id', $itemIds))
                ->orWhere(fn (Builder $q) => $q->where('translatable_type', MenuOptionGroup::class)->whereIn('translatable_id', $groupIds))
                ->orWhere(fn (Builder $q) => $q->where('translatable_type', MenuOptionGroupOption::class)->whereIn('translatable_id', $optionIds));
        };

        $key = fn ($t): string => "{$t->translatable_type}:{$t->translatable_id}:{$t->field_id}";

        $initialKeys = Translation::where($scope)->where('is_initial', true)
            ->get(['translatable_type', 'translatable_id', 'field_id'])->map($key);
        $targetKeys = Translation::where($scope)->where('locale', $target)
            ->get(['translatable_type', 'translatable_id', 'field_id'])->map($key)->flip();

        $missing = $initialKeys->reject(fn (string $k): bool => $targetKeys->has($k));
        if ($missing->isNotEmpty()) {
            throw new HttpException(422, "Translate the menu to '{$target}' before making it the original language.");
        }

        DB::transaction(function () use ($menu, $target, $scope): void {
            // Demote every current source row in another locale, then promote the
            // target rows. Mass updates bypass Eloquent events → no re-translation.
            Translation::where($scope)->where('is_initial', true)->where('locale', '!=', $target)
                ->update(['is_initial' => false]);
            Translation::where($scope)->where('locale', $target)
                ->update(['is_initial' => true]);

            $menu->update(['source_locale' => $target]);
        });
    }
}
