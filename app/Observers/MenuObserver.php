<?php

namespace App\Observers;

use App\Jobs\DeleteImageFilesJob;
use App\Models\Menu;
use App\Models\MenuAddon;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\MenuVariation;
use App\Models\MenuVariationOption;
use App\Models\Translation;
use App\Services\ImageProcessor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MenuObserver
{
    /** @var array<int, array<string, array<int, string>>> */
    private static array $pendingPaths = [];

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
        $variationIds = DB::table('menu_variations')->where('menu_id', $menu->id)->pluck('id');
        $variationOptionIds = $variationIds->isEmpty()
            ? collect()
            : DB::table('menu_variation_options')->whereIn('variation_id', $variationIds)->pluck('id');
        $addonIds = DB::table('menu_addons')->where('menu_id', $menu->id)->pluck('id');

        Translation::query()
            ->where(function ($q) use ($sectionIds, $itemIds, $variationIds, $variationOptionIds, $addonIds) {
                $q->where(fn ($w) => $w->where('translatable_type', MenuSection::class)
                    ->whereIn('translatable_id', $sectionIds));
                if ($itemIds->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('translatable_type', MenuItem::class)
                        ->whereIn('translatable_id', $itemIds));
                }
                if ($variationIds->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('translatable_type', MenuVariation::class)
                        ->whereIn('translatable_id', $variationIds));
                }
                if ($variationOptionIds->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('translatable_type', MenuVariationOption::class)
                        ->whereIn('translatable_id', $variationOptionIds));
                }
                if ($addonIds->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('translatable_type', MenuAddon::class)
                        ->whereIn('translatable_id', $addonIds));
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
