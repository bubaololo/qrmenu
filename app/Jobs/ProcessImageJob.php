<?php

namespace App\Jobs;

use App\Services\AnalysisEventBroker;
use App\Services\ImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessImageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $modelClass,
        public int $modelId,
        public int $restaurantId,
        public string $tempPath,
        public string $targetDir,
        public string $baseName,
        public ?string $oldImagePath = null,
        public string $fieldName = 'image',
        public string $profile = 'default',
    ) {}

    public function handle(ImageProcessor $processor): void
    {
        $tmpFile = null;
        $disk = config('image.disk');
        $originalsDisk = config('image.originals_disk');

        try {
            if (! Storage::disk($originalsDisk)->exists($this->tempPath)) {
                $expectedPath = $this->targetDir.'/'.$this->baseName.'.'.config('image.format');
                $model = $this->modelClass::find($this->modelId);

                // Idempotent re-run: a prior attempt already processed the image
                // (which deletes the original), or the target row is gone. Nothing
                // left to do — don't treat this as a failure.
                if ($model === null || $model->{$this->fieldName} === $expectedPath) {
                    Log::debug('ProcessImageJob: original already consumed or model gone, skipping', [
                        'model' => $this->modelClass,
                        'modelId' => $this->modelId,
                        'baseName' => $this->baseName,
                    ]);

                    return;
                }

                // The original is gone but the image was never written: a genuine
                // loss. Fail loudly so it lands in failed_jobs and failed() runs,
                // instead of silently returning success and stranding the upload.
                throw new \RuntimeException(sprintf(
                    'ProcessImageJob: original "%s" missing and %s#%d image was never written',
                    $this->tempPath,
                    class_basename($this->modelClass),
                    $this->modelId,
                ));
            }

            $content = Storage::disk($originalsDisk)->get($this->tempPath);
            $tmpFile = tempnam(sys_get_temp_dir(), 'img_');
            file_put_contents($tmpFile, $content);

            [$mainPath] = $processor->processAndStore(
                $tmpFile,
                $this->targetDir,
                $this->baseName,
                $this->profile,
            );

            $model = $this->modelClass::findOrFail($this->modelId);
            $model->update([$this->fieldName => $mainPath]);

            Storage::disk($originalsDisk)->delete($this->tempPath);

            if ($this->oldImagePath) {
                $this->deleteOldFiles($processor, $disk);
            }

            // Notify the restaurant admin UI the processed image is now live, so
            // it can swap the placeholder without long-polling the predicted URL.
            app(AnalysisEventBroker::class)->publish(
                "restaurant.{$this->restaurantId}",
                'image.processed',
                [
                    'model_class' => $this->modelClass,
                    'model_id' => $this->modelId,
                    'field' => $this->fieldName,
                    'image_url' => Storage::disk($disk)->url($mainPath),
                    'thumb_url' => Storage::disk($disk)->url($processor->thumbPath($mainPath)),
                ],
            );
        } catch (\Exception $e) {
            Log::error('ProcessImageJob: failed', [
                'model' => $this->modelClass,
                'modelId' => $this->modelId,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            // Terminal cleanup of the temp original is handled by failed(), which
            // also fires when the worker is hard-killed (OOM/SIGKILL) and this
            // catch block never runs.
            throw $e;
        } finally {
            if ($tmpFile !== null && file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Terminal handler invoked by the queue once the job has permanently failed
     * (retries exhausted, timeout, or worker killed). Logs loudly and removes the
     * uploaded original so a failed upload never strands an orphan on disk.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('ProcessImageJob: permanently failed', [
            'model' => $this->modelClass,
            'modelId' => $this->modelId,
            'baseName' => $this->baseName,
            'tempPath' => $this->tempPath,
            'message' => $e->getMessage(),
            'file' => $e->getFile().':'.$e->getLine(),
        ]);

        app(AnalysisEventBroker::class)->publish(
            "restaurant.{$this->restaurantId}",
            'image.failed',
            [
                'model_class' => $this->modelClass,
                'model_id' => $this->modelId,
                'field' => $this->fieldName,
                'error' => $e->getMessage(),
            ],
        );

        $originalsDisk = config('image.originals_disk');
        if (Storage::disk($originalsDisk)->exists($this->tempPath)) {
            Storage::disk($originalsDisk)->delete($this->tempPath);
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
