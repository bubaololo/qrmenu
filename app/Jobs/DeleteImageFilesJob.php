<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DeleteImageFilesJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    /**
     * @param  array<string, array<int, string>>  $pathsByDisk  Map of disk name to list of paths to delete.
     */
    public function __construct(public array $pathsByDisk) {}

    public function handle(): void
    {
        foreach ($this->pathsByDisk as $disk => $paths) {
            $paths = array_values(array_filter(array_unique($paths)));
            if ($paths === []) {
                continue;
            }

            $storage = Storage::disk($disk);
            $storage->delete($paths);

            // Sweep parent directories that became empty (e.g. `menu-items/{menu_id}/`
            // left over after cropped item images are removed). Only sub-directories
            // are touched — top-level dirs like `menu-items`, `restaurants`, `logos`
            // are kept so new uploads don't have to recreate them.
            $dirs = array_unique(array_map('dirname', $paths));
            rsort($dirs); // deepest first so nested empties collapse upward

            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '' || ! str_contains($dir, '/')) {
                    continue;
                }

                if ($storage->allFiles($dir) === [] && $storage->directories($dir) === []) {
                    $storage->deleteDirectory($dir);
                }
            }
        }
    }
}
