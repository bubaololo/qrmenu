<?php

namespace App\Jobs;

use App\Actions\AnalyzeMenuImageAction;
use App\Actions\SaveMenuAnalysisAction;
use App\Enums\MenuAnalysisStatus;
use App\Models\Menu;
use App\Models\MenuAnalysis;
use App\Services\LlmCascadeService;
use App\Support\MenuJson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
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

        $imageCount = $this->analysis->image_count;
        $chunkWhenGt = (int) config('llm.thresholds.chunk_when_images_gt', 5);

        if ($imageCount > $chunkWhenGt) {
            $this->dispatchChunkChain();

            return;
        }

        $this->analysis->markProcessing();

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

            if ($savedMenu) {
                $this->storeConfidenceInRedis($savedMenu, $menuData);
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

    /**
     * Create an empty Menu shell up-front, then fan out chunks in parallel via Bus::batch.
     * Every chunk uses `SaveMenuAnalysisAction::appendChunk()`, no special chunk-0 path.
     * On success the batch's then() hook runs FinalizeAnalysisJob; on any chunk's retry
     * exhaustion the catch() hook marks the analysis failed and cleans up images.
     */
    private function dispatchChunkChain(): void
    {
        $chunkSize = (int) config('llm.thresholds.chunk_size', 4);
        $pathChunks = array_chunk($this->analysis->image_paths, $chunkSize);
        $total = count($pathChunks);

        $this->analysis->markProcessing();

        $menu = Menu::create([
            'restaurant_id' => $this->analysis->restaurant_id,
            'source_locale' => null,
            'source_images_count' => count($this->analysis->image_paths),
            'is_active' => false,
            'detected_date' => now()->toDateString(),
        ]);
        $this->analysis->update(['result_menu_id' => $menu->id]);

        $offset = 0;
        $jobs = [];
        foreach ($pathChunks as $i => $paths) {
            $jobs[] = new AnalyzeChunkJob(
                $this->analysis,
                $paths,
                $i,
                $total,
                $offset,
            );
            $offset += count($paths);
        }

        Log::channel('llm')->info('Dispatching chunk batch', [
            'analysis_uuid' => $this->analysis->uuid,
            'total_images' => count($this->analysis->image_paths),
            'chunk_count' => $total,
            'chunk_size' => $chunkSize,
            'menu_id' => $menu->id,
        ]);

        $analysisId = $this->analysis->id;

        Bus::batch($jobs)
            ->name("menu-analysis-{$this->analysis->uuid}")
            ->onQueue(config('llm.queue', 'llm-analysis'))
            ->then(function () use ($analysisId): void {
                FinalizeAnalysisJob::dispatch(MenuAnalysis::findOrFail($analysisId));
            })
            ->catch(function ($batch, Throwable $e) use ($analysisId): void {
                $analysis = MenuAnalysis::find($analysisId);
                if (! $analysis) {
                    return;
                }
                $analysis->markFailed("Batch failed: {$e->getMessage()}");

                $disk = Storage::disk($analysis->image_disk);
                foreach ($analysis->image_paths as $path) {
                    $disk->delete($path);
                }
                foreach ($analysis->original_image_paths ?? [] as $path) {
                    $disk->delete($path);
                }
            })
            ->dispatch();
    }

    private function storeConfidenceInRedis(Menu $menu, array $menuData): void
    {
        $menu->load('sections.items');

        $confidenceMap = [];

        foreach ($menu->sections as $sIdx => $section) {
            $llmItems = $menuData['sections'][$sIdx]['items'] ?? [];
            $items = $section->items->sortBy('sort_order')->values();

            foreach ($items as $iIdx => $item) {
                $llmItem = $llmItems[$iIdx] ?? null;
                if (! $llmItem) {
                    continue;
                }

                $textConf = is_numeric($llmItem['item_confidence'] ?? null) ? (float) $llmItem['item_confidence'] : null;
                $bboxConf = is_numeric($llmItem['image_bbox']['confidence'] ?? null) ? (float) $llmItem['image_bbox']['confidence'] : null;

                if ($textConf !== null || $bboxConf !== null) {
                    $confidenceMap[$item->id] = array_filter(
                        ['text' => $textConf, 'bbox' => $bboxConf],
                        fn ($v) => $v !== null,
                    );
                }
            }
        }

        if (! empty($confidenceMap)) {
            Redis::setex('menu:confidence:'.$menu->id, 86400 * 7, json_encode($confidenceMap));
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
