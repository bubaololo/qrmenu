<?php

namespace App\Observers;

use App\Jobs\DeleteImageFilesJob;
use App\Models\MenuAnalysis;

class MenuAnalysisObserver
{
    /** @var array<int, array<string, array<int, string>>> */
    private static array $pendingPaths = [];

    public function deleting(MenuAnalysis $analysis): void
    {
        $imageDisk = $analysis->image_disk ?: 'public';
        $originalsDisk = config('image.originals_disk');

        $imagePaths = is_array($analysis->image_paths) ? $analysis->image_paths : [];
        $originalPaths = is_array($analysis->original_image_paths) ? $analysis->original_image_paths : [];

        $paths = [];
        if ($imagePaths !== []) {
            $paths[$imageDisk] = $imagePaths;
        }
        if ($originalPaths !== []) {
            $paths[$originalsDisk] = array_merge($paths[$originalsDisk] ?? [], $originalPaths);
        }

        if ($paths !== []) {
            self::$pendingPaths[$analysis->id] = $paths;
        }
    }

    public function deleted(MenuAnalysis $analysis): void
    {
        $paths = self::$pendingPaths[$analysis->id] ?? null;
        unset(self::$pendingPaths[$analysis->id]);

        if ($paths) {
            DeleteImageFilesJob::dispatch($paths);
        }
    }
}
