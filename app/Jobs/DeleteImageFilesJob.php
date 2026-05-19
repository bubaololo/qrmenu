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

            Storage::disk($disk)->delete($paths);
        }
    }
}
