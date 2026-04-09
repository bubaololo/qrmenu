<?php

namespace App\Jobs;

use App\Services\ImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessImageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $modelClass,
        public int $modelId,
        public string $tempPath,
        public string $targetDir,
        public string $baseName,
        public ?string $oldImagePath = null,
    ) {}

    public function handle(ImageProcessor $processor): void
    {
        $tmpFile = null;
        $disk = config('image.disk');

        try {
            if (! Storage::disk($disk)->exists($this->tempPath)) {
                return;
            }

            $content = Storage::disk($disk)->get($this->tempPath);
            $tmpFile = tempnam(sys_get_temp_dir(), 'img_');
            file_put_contents($tmpFile, $content);

            [$mainPath] = $processor->processAndStore(
                $tmpFile,
                $this->targetDir,
                $this->baseName,
            );

            $model = $this->modelClass::findOrFail($this->modelId);
            $model->update(['image' => $mainPath]);

            Storage::disk($disk)->delete($this->tempPath);

            if ($this->oldImagePath) {
                $this->deleteOldFiles($processor, $disk);
            }
        } catch (\Exception $e) {
            if ($this->attempts() >= $this->tries) {
                Storage::disk($disk)->delete($this->tempPath);
            }

            throw $e;
        } finally {
            if ($tmpFile !== null && file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    private function deleteOldFiles(ImageProcessor $processor, string $disk): void
    {
        if (Storage::disk($disk)->exists($this->oldImagePath)) {
            Storage::disk($disk)->delete($this->oldImagePath);
        }

        $thumb = $processor->thumbPath($this->oldImagePath);
        if (Storage::disk($disk)->exists($thumb)) {
            Storage::disk($disk)->delete($thumb);
        }
    }
}
