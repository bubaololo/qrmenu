<?php

namespace App\Observers;

use App\Actions\ForgetMenuPageCache;
use App\Jobs\DeleteImageFilesJob;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Translation;
use App\Services\ImageProcessor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MenuSectionObserver
{
    /** @var array<int, array<string, array<int, string>>> */
    private static array $pendingPaths = [];

    public function saved(MenuSection $section): void
    {
        app(ForgetMenuPageCache::class)->forModel($section);
    }

    /**
     * The section's own translations are handled by the HasTranslations trait.
     * Before FK CASCADE wipes descendant rows:
     *   1. Bulk-delete polymorphic translations of descendant items.
     *   2. Collect menu_items.image paths so the post-delete event can dispatch cleanup.
     *
     * Option groups are menu-scoped (shared across sections), so they are NOT
     * deleted with a section — only the item↔group pivot rows cascade. Group
     * translation cleanup happens on menu/restaurant deletion.
     */
    public function deleting(MenuSection $section): void
    {
        $itemIds = DB::table('menu_items')->where('section_id', $section->id)->pluck('id');

        if ($itemIds->isNotEmpty()) {
            Translation::query()
                ->where('translatable_type', MenuItem::class)
                ->whereIn('translatable_id', $itemIds)
                ->delete();
        }

        $paths = $this->collectItemImagePaths($itemIds);
        if ($paths !== []) {
            self::$pendingPaths[$section->id] = $paths;
        }
    }

    public function deleted(MenuSection $section): void
    {
        $paths = self::$pendingPaths[$section->id] ?? null;
        unset(self::$pendingPaths[$section->id]);

        if ($paths) {
            DeleteImageFilesJob::dispatch($paths);
        }

        app(ForgetMenuPageCache::class)->forModel($section);
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
