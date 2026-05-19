<?php

namespace App\Observers;

use App\Jobs\DeleteImageFilesJob;
use App\Models\MenuItem;
use App\Services\ImageProcessor;

class MenuItemObserver
{
    /** @var array<int, array<string, array<int, string>>> */
    private static array $pendingPaths = [];

    /**
     * Capture file paths before the row is deleted so the post-delete event can dispatch cleanup.
     * Only relevant for direct $item->delete(); when MenuItem rows are wiped via FK CASCADE from
     * a parent (section/menu/restaurant), the parent observer collects the paths instead.
     */
    public function deleting(MenuItem $item): void
    {
        if (! $item->image) {
            return;
        }

        $processor = app(ImageProcessor::class);
        $disk = config('image.disk');

        self::$pendingPaths[$item->id] = [
            $disk => [$item->image, $processor->thumbPath($item->image)],
        ];
    }

    public function deleted(MenuItem $item): void
    {
        $paths = self::$pendingPaths[$item->id] ?? null;
        unset(self::$pendingPaths[$item->id]);

        if ($paths) {
            DeleteImageFilesJob::dispatch($paths);
        }
    }
}
