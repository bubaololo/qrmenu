<?php

namespace App\Observers;

use App\Jobs\DeleteImageFilesJob;
use App\Models\Zone;
use App\Services\ImageProcessor;

class ZoneObserver
{
    /** @var array<int, array<string, array<int, string>>> */
    private static array $pendingPaths = [];

    public function deleting(Zone $zone): void
    {
        if (! $zone->image) {
            return;
        }

        $processor = app(ImageProcessor::class);
        $disk = config('image.disk');

        self::$pendingPaths[$zone->id] = [
            $disk => [$zone->image, $processor->thumbPath($zone->image)],
        ];
    }

    public function deleted(Zone $zone): void
    {
        $paths = self::$pendingPaths[$zone->id] ?? null;
        unset(self::$pendingPaths[$zone->id]);

        if ($paths) {
            DeleteImageFilesJob::dispatch($paths);
        }
    }
}
