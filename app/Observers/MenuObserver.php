<?php

namespace App\Observers;

use App\Actions\ForgetMenuPageCache;
use App\Jobs\DeleteImageFilesJob;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Translation;
use App\Services\ImageProcessor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MenuObserver
{
    /** @var array<int, array<string, array<int, string>>> */
    private static array $pendingPaths = [];

    /**
     * Created (a new menu version replaces the one currently served) or updated
     * (e.g. source_locale change) — drop the restaurant's stale menu pages.
     */
    public function saved(Menu $menu): void
    {
        app(ForgetMenuPageCache::class)->forModel($menu);
    }

    /**
     * Menu has no own translations (model does not use HasTranslations).
     * Before FK CASCADE wipes descendant rows:
     *   1. Bulk-delete polymorphic translations of all descendant sections/items/variations/options/add-ons.
     *   2. Collect menu_items.image paths so the post-delete event can dispatch cleanup.
     */
    public function deleting(Menu $menu): void
    {
        $sectionIds = DB::table('menu_sections')->where('menu_id', $menu->id)->pluck('id');
        if ($sectionIds->isEmpty()) {
            return;
        }

        $itemIds = DB::table('menu_items')->whereIn('section_id', $sectionIds)->pluck('id');
        $groupIds = DB::table('modifier_groups')->where('menu_id', $menu->id)->pluck('id');
        $optionIds = $groupIds->isEmpty()
            ? collect()
            : DB::table('modifier_options')->whereIn('group_id', $groupIds)->pluck('id');

        Translation::query()
            ->where(function ($q) use ($sectionIds, $itemIds, $groupIds, $optionIds) {
                $q->where(fn ($w) => $w->where('translatable_type', MenuSection::class)
                    ->whereIn('translatable_id', $sectionIds));
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
            })
            ->delete();

        $paths = $this->collectItemImagePaths($itemIds);
        if ($paths !== []) {
            self::$pendingPaths[$menu->id] = $paths;
        }
    }

    public function deleted(Menu $menu): void
    {
        $paths = self::$pendingPaths[$menu->id] ?? null;
        unset(self::$pendingPaths[$menu->id]);

        if ($paths) {
            DeleteImageFilesJob::dispatch($paths);
        }

        app(ForgetMenuPageCache::class)->forModel($menu);
    }

    /**
     * @param  Collection<int, int>  $itemIds
     * @return array<string, array<int, string>>
     */
    private function collectItemImagePaths($itemIds): array
    {
        if ($itemIds->isEmpty()) {
            return [];
        }

        $processor = app(ImageProcessor::class);
        $disk = config('image.disk');
        $paths = [];

        DB::table('menu_items')
            ->whereIn('id', $itemIds)
            ->whereNotNull('image')
            ->pluck('image')
            ->each(function (string $image) use (&$paths, $processor): void {
                $paths[] = $image;
                $paths[] = $processor->thumbPath($image);
            });

        return $paths === [] ? [] : [$disk => $paths];
    }
}
