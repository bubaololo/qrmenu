<?php

use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retire the 'mixed' source: every menu must have exactly one concrete original
 * language whose rows carry is_initial.
 *
 * Two legacy shapes exist:
 *  - source_locale='mixed' but the is_initial rows already sit under a concrete
 *    locale (e.g. menu 56 → 'en'): just fix the column.
 *  - source_locale='mixed' AND the is_initial rows are literally stored under
 *    locale='mixed' (menus 37/38), with full en/ru/vi translations derived from
 *    them: promote the concrete language (English) to the original, dropping the
 *    'mixed' source rows (the English translation faithfully represents them).
 *
 * For each affected menu we resolve a concrete target, merge/drop any
 * locale='mixed' rows, re-point is_initial to the target across every entity,
 * and set source_locale.
 */
return new class extends Migration
{
    public function up(): void
    {
        $menus = DB::table('menus')->where('source_locale', 'mixed')->get(['id', 'restaurant_id']);

        foreach ($menus as $menu) {
            [$sectionIds, $itemIds, $groupIds, $optionIds] = $this->entityIds($menu->id);
            $scope = $this->scope($sectionIds, $itemIds, $groupIds, $optionIds);

            $target = $this->resolveTarget($scope, $menu->restaurant_id);

            DB::transaction(function () use ($menu, $scope, $target): void {
                $this->cleanupMixedRows($scope, $target);

                // Re-point is_initial to the target: demote every other locale,
                // then promote the target. Direct DB → no observer / re-translation.
                Translation::where($scope)->where('is_initial', true)->where('locale', '!=', $target)
                    ->update(['is_initial' => false]);
                Translation::where($scope)->where('locale', $target)
                    ->update(['is_initial' => true]);

                DB::table('menus')->where('id', $menu->id)->update(['source_locale' => $target]);
            });
        }

        $this->purgeOrphanedMixedRows();
    }

    /**
     * Delete leftover locale='mixed' rows whose owning entity no longer exists
     * (orphans from menus that were re-analyzed before cascade cleanup existed).
     * Strictly scoped to orphaned 'mixed' rows — they belong to no menu.
     */
    private function purgeOrphanedMixedRows(): void
    {
        $tables = [
            MenuSection::class => 'menu_sections',
            MenuItem::class => 'menu_items',
            MenuOptionGroup::class => 'menu_option_groups',
            MenuOptionGroupOption::class => 'menu_option_group_options',
        ];

        foreach ($tables as $type => $table) {
            Translation::where('locale', 'mixed')
                ->where('translatable_type', $type)
                ->whereNotIn('translatable_id', DB::table($table)->select('id'))
                ->delete();
        }
    }

    public function down(): void
    {
        // One-way data fix; 'mixed' is intentionally not restored.
    }

    /** @return array{Collection,Collection,Collection,Collection} */
    private function entityIds(int $menuId): array
    {
        $sectionIds = DB::table('menu_sections')->where('menu_id', $menuId)->pluck('id');
        $itemIds = DB::table('menu_items')->whereIn('section_id', $sectionIds)->pluck('id');
        $groupIds = DB::table('menu_option_groups')->whereIn('section_id', $sectionIds)->pluck('id');
        $optionIds = DB::table('menu_option_group_options')->whereIn('group_id', $groupIds)->pluck('id');

        return [$sectionIds, $itemIds, $groupIds, $optionIds];
    }

    private function scope(
        Collection $sectionIds,
        Collection $itemIds,
        Collection $groupIds,
        Collection $optionIds,
    ): Closure {
        return function (Builder $q) use ($sectionIds, $itemIds, $groupIds, $optionIds): void {
            $q->where(fn (Builder $q) => $q->where('translatable_type', MenuSection::class)->whereIn('translatable_id', $sectionIds))
                ->orWhere(fn (Builder $q) => $q->where('translatable_type', MenuItem::class)->whereIn('translatable_id', $itemIds))
                ->orWhere(fn (Builder $q) => $q->where('translatable_type', MenuOptionGroup::class)->whereIn('translatable_id', $groupIds))
                ->orWhere(fn (Builder $q) => $q->where('translatable_type', MenuOptionGroupOption::class)->whereIn('translatable_id', $optionIds));
        };
    }

    /**
     * Concrete original language: the dominant is_initial locale that isn't
     * 'mixed'; else English when present; else the restaurant's primary_language
     * (when concrete); else 'en'.
     */
    private function resolveTarget(Closure $scope, int $restaurantId): string
    {
        $dominant = Translation::where($scope)->where('is_initial', true)->where('locale', '!=', 'mixed')
            ->select('locale', DB::raw('count(*) as c'))->groupBy('locale')->orderByDesc('c')->value('locale');
        if ($dominant) {
            return $dominant;
        }

        if (Translation::where($scope)->where('locale', 'en')->exists()) {
            return 'en';
        }

        $primary = DB::table('restaurants')->where('id', $restaurantId)->value('primary_language');

        return ($primary && $primary !== 'mixed') ? $primary : 'en';
    }

    /**
     * Drop locale='mixed' rows where the target already has the field; otherwise
     * relabel them to the target so no field loses its source.
     */
    private function cleanupMixedRows(Closure $scope, string $target): void
    {
        $targetKeys = Translation::where($scope)->where('locale', $target)
            ->get(['translatable_type', 'translatable_id', 'field_id'])
            ->map(fn ($t) => "{$t->translatable_type}:{$t->translatable_id}:{$t->field_id}")->flip();

        $mixed = Translation::where($scope)->where('locale', 'mixed')
            ->get(['id', 'translatable_type', 'translatable_id', 'field_id']);

        $deleteIds = [];
        $renameIds = [];
        foreach ($mixed as $row) {
            $key = "{$row->translatable_type}:{$row->translatable_id}:{$row->field_id}";
            if ($targetKeys->has($key)) {
                $deleteIds[] = $row->id;
            } else {
                $renameIds[] = $row->id;
            }
        }

        if ($deleteIds) {
            Translation::whereIn('id', $deleteIds)->delete();
        }
        if ($renameIds) {
            Translation::whereIn('id', $renameIds)->update(['locale' => $target]);
        }
    }
};
