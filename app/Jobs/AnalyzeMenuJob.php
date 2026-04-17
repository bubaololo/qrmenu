<?php

namespace App\Jobs;

use App\Actions\AnalyzeMenuImageAction;
use App\Actions\SaveMenuAnalysisAction;
use App\Enums\MenuAnalysisStatus;
use App\Models\MenuAnalysis;
use App\Services\LlmCascadeService;
use App\Support\MenuJson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnalyzeMenuJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public MenuAnalysis $analysis,
    ) {
        $this->timeout = (int) config('llm.job_timeout', 540);
        $this->onQueue(config('llm.queue', 'llm-analysis'));
    }

    public function handle(
        AnalyzeMenuImageAction $action,
        LlmCascadeService $cascade,
        SaveMenuAnalysisAction $saveAction,
    ): void {
        ini_set('memory_limit', '512M');

        $this->analysis->markProcessing();

        $imageCount = $this->analysis->image_count;
        $providers = $cascade->resolveProviders($imageCount, $this->analysis->vision_model);

        Log::channel('llm')->info('Job started', [
            'analysis_uuid' => $this->analysis->uuid,
            'image_count' => $imageCount,
            'vision_model' => $this->analysis->vision_model ?? 'auto (cascade)',
            'resolved_providers' => array_map(
                fn ($p) => $p->provider()->value.':'.$p->model(),
                $providers,
            ),
        ]);

        try {
            $built = $action->buildMessages(
                $this->analysis->image_paths,
                $this->analysis->image_disk,
            );

            $result = $cascade->executeWithFallback(
                $built['messages'],
                $providers,
                $this->analysis,
                [
                    'image_count' => $imageCount,
                    'prompt_id' => $built['prompt']->id,
                    'prompt_name' => $built['prompt']->name,
                    'paths' => $this->analysis->image_paths,
                ],
            );

            $menuData = MenuJson::decodeMenuFromLlmText($result['text']);
            $itemCount = MenuJson::dishCount($menuData);

            $savedMenu = null;
            if ($this->analysis->restaurant_id) {
                $savedMenu = $saveAction->handle(
                    $menuData,
                    $this->analysis->restaurant_id,
                    $imageCount,
                );
            }

            // Dispatch crop job if menu saved and originals available for bbox extraction
            if ($savedMenu && ! empty($this->analysis->original_image_paths)) {
                CropMenuItemImagesJob::dispatch(
                    $savedMenu->id,
                    $this->analysis->original_image_paths,
                    $this->analysis->image_disk,
                );
            }

            $this->analysis->markCompleted($savedMenu, $menuData, $itemCount);
        } catch (Throwable $e) {
            $this->analysis->markFailed($e->getMessage());
        } finally {
            $this->cleanupImages();
        }
    }

    private function cleanupImages(): void
    {
        $disk = Storage::disk($this->analysis->image_disk);

        // Delete preprocessed images
        foreach ($this->analysis->image_paths as $path) {
            $disk->delete($path);
        }

        // Delete originals only if crop job was NOT dispatched (failure case)
        // When crop job IS dispatched, it handles original cleanup after cropping
        if ($this->analysis->status !== MenuAnalysisStatus::Completed) {
            foreach ($this->analysis->original_image_paths ?? [] as $path) {
                $disk->delete($path);
            }
        }
    }
}
