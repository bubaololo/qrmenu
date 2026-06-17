<?php

namespace App\Observers;

use App\Enums\RestaurantUserRole;
use App\Jobs\DeleteImageFilesJob;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Models\Zone;
use App\Services\ImageProcessor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RestaurantObserver
{
    /** @var array<int, array<string, array<int, string>>> */
    private static array $pendingPaths = [];

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
     * Pre-DELETE hook that runs before FK CASCADE wipes descendant rows.
     *
     * Performs two things that cannot be done after CASCADE has fired (Eloquent emits no
     * events for cascade-deleted child rows):
     *   1. Bulk-delete polymorphic translations of all descendants (menus/sections/items/
     *      groups/options/zones). The trait HasTranslations handles the restaurant's own.
     *   2. Collect image/logo paths from the restaurant itself and from every descendant
     *      that stores files (zones, menu_items, menu_analyses) so the post-delete event
     *      can dispatch a single cleanup job.
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
        $groupIds = $menuIds->isEmpty()
            ? collect()
            : DB::table('modifier_groups')->whereIn('menu_id', $menuIds)->pluck('id');
        $optionIds = $groupIds->isEmpty()
            ? collect()
            : DB::table('modifier_options')->whereIn('group_id', $groupIds)->pluck('id');
        $zoneIds = DB::table('zones')->where('restaurant_id', $restaurant->id)->pluck('id');

        if ($sectionIds->isNotEmpty() || $itemIds->isNotEmpty() || $groupIds->isNotEmpty()
            || $optionIds->isNotEmpty() || $zoneIds->isNotEmpty()) {
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
                        $q->orWhere(fn ($w) => $w->where('translatable_type', ModifierGroup::class)
                            ->whereIn('translatable_id', $groupIds));
                    }
                    if ($optionIds->isNotEmpty()) {
                        $q->orWhere(fn ($w) => $w->where('translatable_type', ModifierOption::class)
                            ->whereIn('translatable_id', $optionIds));
                    }
                    if ($zoneIds->isNotEmpty()) {
                        $q->orWhere(fn ($w) => $w->where('translatable_type', Zone::class)
                            ->whereIn('translatable_id', $zoneIds));
                    }
                })
                ->delete();
        }

        $paths = $this->collectFilePaths($restaurant, $zoneIds, $itemIds);
        if ($paths !== []) {
            self::$pendingPaths[$restaurant->id] = $paths;
        }
    }

    public function deleted(Restaurant $restaurant): void
    {
        $paths = self::$pendingPaths[$restaurant->id] ?? null;
        unset(self::$pendingPaths[$restaurant->id]);

        if ($paths) {
            DeleteImageFilesJob::dispatch($paths);
        }
    }

    /**
     * @param  Collection<int, int>  $zoneIds
     * @param  Collection<int, int>  $itemIds
     * @return array<string, array<int, string>>
     */
    private function collectFilePaths(Restaurant $restaurant, $zoneIds, $itemIds): array
    {
        $processor = app(ImageProcessor::class);
        $publicDisk = config('image.disk');
        $originalsDisk = config('image.originals_disk');
        $byDisk = [];

        $add = function (string $disk, string $path) use (&$byDisk, $processor): void {
            $byDisk[$disk][] = $path;
            $byDisk[$disk][] = $processor->thumbPath($path);
        };

        if ($restaurant->image) {
            $add($publicDisk, $restaurant->image);
        }
        if ($restaurant->logo) {
            $add($publicDisk, $restaurant->logo);
        }

        if ($zoneIds->isNotEmpty()) {
            DB::table('zones')
                ->whereIn('id', $zoneIds)
                ->whereNotNull('image')
                ->pluck('image')
                ->each(fn (string $image) => $add($publicDisk, $image));
        }

        if ($itemIds->isNotEmpty()) {
            DB::table('menu_items')
                ->whereIn('id', $itemIds)
                ->whereNotNull('image')
                ->pluck('image')
                ->each(fn (string $image) => $add($publicDisk, $image));
        }

        DB::table('menu_analyses')
            ->where('restaurant_id', $restaurant->id)
            ->get(['image_paths', 'original_image_paths', 'image_disk'])
            ->each(function ($analysis) use (&$byDisk, $originalsDisk): void {
                $imageDisk = $analysis->image_disk ?: 'public';

                foreach ((json_decode((string) ($analysis->image_paths ?? ''), true) ?: []) as $p) {
                    $byDisk[$imageDisk][] = $p;
                }
                foreach ((json_decode((string) ($analysis->original_image_paths ?? ''), true) ?: []) as $p) {
                    $byDisk[$originalsDisk][] = $p;
                }
            });

        return $byDisk;
    }
}
