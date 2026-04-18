<?php

namespace App\Jobs;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\ImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;

class CropMenuItemImagesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  string[]  $originalImagePaths
     */
    public function __construct(
        public int $menuId,
        public array $originalImagePaths,
        public string $imageDisk,
    ) {}

    public function handle(ImageProcessor $processor): void
    {
        $menu = Menu::with('sections.items')->find($this->menuId);

        if (! $menu) {
            $this->cleanupOriginals();

            return;
        }

        $cropped = 0;

        foreach ($menu->sections as $section) {
            $section->setRelation('menu', $menu);
            foreach ($section->items as $item) {
                $item->setRelation('section', $section);
                if ($this->cropItem($item, $processor)) {
                    $cropped++;
                }
            }
        }

        Log::channel('llm')->info('Bbox crop complete', [
            'menu_id' => $this->menuId,
            'items_cropped' => $cropped,
        ]);

        $this->cleanupOriginals();
    }

    private function cropItem(MenuItem $item, ImageProcessor $processor): bool
    {
        $bbox = $item->image_bbox;

        if (! $bbox || ! isset($bbox['image_index'], $bbox['coords'])) {
            return false;
        }

        if (($bbox['confidence'] ?? 1.0) < 0.5) {
            return false;
        }

        $imgIdx = $bbox['image_index'];

        if (! isset($this->originalImagePaths[$imgIdx])) {
            return false;
        }

        $disk = Storage::disk($this->imageDisk);
        $sourcePath = $disk->path($this->originalImagePaths[$imgIdx]);

        if (! file_exists($sourcePath)) {
            return false;
        }

        try {
            [$x1, $y1, $x2, $y2] = $bbox['coords'];

            $img = new Imagick($sourcePath);
            $w = $img->getImageWidth();
            $h = $img->getImageHeight();

            $cropX = max(0, (int) round($x1 * $w));
            $cropY = max(0, (int) round($y1 * $h));
            $cropW = min((int) round(($x2 - $x1) * $w), $w - $cropX);
            $cropH = min((int) round(($y2 - $y1) * $h), $h - $cropY);

            if ($cropW < 50 || $cropH < 50) {
                $img->destroy();

                return false;
            }

            $img->cropImage($cropW, $cropH, $cropX, $cropY);
            $img->setImagePage(0, 0, 0, 0);

            $tmpPath = tempnam(sys_get_temp_dir(), 'crop_');
            $img->writeImage($tmpPath);
            $img->destroy();

            $targetDir = config('image.paths.menu_items').'/'.$this->menuId;
            $baseName = 'item_'.$item->id;

            [$mainPath] = $processor->processAndStore($tmpPath, $targetDir, $baseName);
            $item->update(['image' => $mainPath]);

            @unlink($tmpPath);

            return true;
        } catch (\Throwable $e) {
            Log::channel('llm')->warning('Bbox crop failed for item', [
                'item_id' => $item->id,
                'menu_id' => $this->menuId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function cleanupOriginals(): void
    {
        $disk = Storage::disk($this->imageDisk);

        foreach ($this->originalImagePaths as $path) {
            $disk->delete($path);
        }
    }
}
