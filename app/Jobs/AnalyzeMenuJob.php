<?php

namespace App\Jobs;

use App\Actions\AnalyzeMenuImageAction;
use App\Actions\SaveMenuAnalysisAction;
use App\Enums\MenuAnalysisStatus;
use App\Models\Menu;
use App\Models\MenuAnalysis;
use App\Services\AnalysisEventBroker;
use App\Services\ImagePreflightApplier;
use App\Services\ImagePreflightService;
use App\Services\ImagePreprocessor;
use App\Services\LlmCascadeService;
use App\Support\MenuJson;
use App\Support\PreflightResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        ImagePreflightService $preflightService,
        ImagePreflightApplier $preflightApplier,
        ImagePreprocessor $preprocessor,
    ): void {
        ini_set('memory_limit', '512M');

        $imageCount = $this->analysis->image_count;
        $chunkWhenGt = (int) config('llm.thresholds.chunk_when_images_gt', 5);
        $chunkSize = (int) config('llm.thresholds.chunk_size', 4);
        $isChunked = $imageCount > $chunkWhenGt;
        $chunkTotal = $isChunked ? (int) ceil($imageCount / $chunkSize) : 1;

        $this->analysis->markProcessing();

        app(AnalysisEventBroker::class)->publish(
            "menu-analysis.{$this->analysis->uuid}",
            'analysis.started',
            [
                'image_count' => $imageCount,
                'chunk_total' => $chunkTotal,
                'chunk_size' => $isChunked ? $chunkSize : $imageCount,
                'menu_id' => $this->analysis->result_menu_id,
            ],
        );

        $this->prepareImages($preflightService, $preflightApplier, $preprocessor);

        if ($isChunked) {
            $this->dispatchChunkChain();

            return;
        }

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

            app(AnalysisEventBroker::class)->publish(
                "menu-analysis.{$this->analysis->uuid}",
                'analysis.vision-start',
                [
                    'image_count' => $imageCount,
                    'providers' => array_map(
                        fn ($p) => $p->provider()->value.':'.$p->model(),
                        $providers,
                    ),
                ],
            );

            $visionStartedAt = microtime(true);
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
            $visionDurationMs = (int) round((microtime(true) - $visionStartedAt) * 1000);

            $menuData = MenuJson::decodeMenuFromLlmText($result['text']);
            $itemCount = MenuJson::dishCount($menuData);

            app(AnalysisEventBroker::class)->publish(
                "menu-analysis.{$this->analysis->uuid}",
                'analysis.vision-complete',
                [
                    'provider' => $result['provider'],
                    'model' => $result['model'],
                    'tier' => $result['tier'],
                    'duration_ms' => $visionDurationMs,
                    'item_count' => $itemCount,
                    'input_tokens' => $result['usage']['input_tokens'] ?? null,
                    'output_tokens' => $result['usage']['output_tokens'] ?? null,
                ],
            );

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

                app(AnalysisEventBroker::class)->publish(
                    "menu-analysis.{$this->analysis->uuid}",
                    'analysis.menu-saved',
                    [
                        'menu_id' => $savedMenu->id,
                        'section_count' => $savedMenu->sections()->count(),
                        'item_count' => $itemCount,
                    ],
                );
            }

            // Dispatch crop job if menu saved and originals available for bbox extraction
            if ($savedMenu && ! empty($this->analysis->original_image_paths)) {
                CropMenuItemImagesJob::dispatch(
                    $savedMenu->id,
                    $this->analysis->original_image_paths,
                    $this->analysis->image_disk,
                    $this->analysis->uuid,
                );
            }

            if ($savedMenu) {
                TranslateMenuJob::dispatchForAllTargetLocales($savedMenu);
            }

            $this->analysis->markCompleted($savedMenu, $menuData, $itemCount);

            app(AnalysisEventBroker::class)->publish(
                "menu-analysis.{$this->analysis->uuid}",
                'analysis.completed',
                [
                    'menu_id' => $savedMenu?->id,
                    'restaurant_id' => $this->analysis->restaurant_id,
                    'item_count' => $itemCount,
                ],
            );
        } catch (Throwable $e) {
            $this->analysis->markFailed($e->getMessage());

            app(AnalysisEventBroker::class)->publish(
                "menu-analysis.{$this->analysis->uuid}",
                'analysis.failed',
                ['error' => $e->getMessage()],
            );
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
        // Refresh paths after prepareImages() updated image_paths to preprocessed.
        $this->analysis->refresh();
        $pathChunks = array_chunk($this->analysis->image_paths, $chunkSize);
        $total = count($pathChunks);

        // Each restaurant has a single menu. Replace the previous one before
        // analysis writes a fresh tree.
        Menu::where('restaurant_id', $this->analysis->restaurant_id)->delete();

        $menu = Menu::create([
            'restaurant_id' => $this->analysis->restaurant_id,
            'source_locale' => null,
            'source_images_count' => count($this->analysis->image_paths),
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
        $analysisUuid = $this->analysis->uuid;

        Bus::batch($jobs)
            ->name("menu-analysis-{$this->analysis->uuid}")
            ->onQueue(config('llm.queue', 'llm-analysis'))
            ->then(function () use ($analysisId): void {
                FinalizeAnalysisJob::dispatch(MenuAnalysis::findOrFail($analysisId));
            })
            ->catch(function ($batch, Throwable $e) use ($analysisId, $analysisUuid): void {
                $analysis = MenuAnalysis::find($analysisId);
                if (! $analysis) {
                    return;
                }

                // Late retry crashes (e.g. mime_content_type failing on a prep file already
                // cleaned up by FinalizeAnalysisJob) should not flip a completed analysis
                // back to failed. The batch is technically failed from Laravel's perspective,
                // but functionally the analysis is done.
                if ($analysis->status === MenuAnalysisStatus::Completed) {
                    Log::channel('llm')->warning('Batch catch hook fired after analysis completion — ignoring', [
                        'analysis_uuid' => $analysisUuid,
                        'error' => $e->getMessage(),
                    ]);

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

                app(AnalysisEventBroker::class)->publish(
                    "menu-analysis.{$analysisUuid}",
                    'analysis.failed',
                    ['error' => $e->getMessage()],
                );
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

    /**
     * Run preflight (rotation/bbox detection + apply) and preprocessing (trim/deskew/webp)
     * on the original uploads. Streams progress as SSE events. Updates analysis.image_paths
     * to the preprocessed paths so downstream stages (chunked dispatch, vision LLM) consume them.
     */
    private function prepareImages(
        ImagePreflightService $preflightService,
        ImagePreflightApplier $preflightApplier,
        ImagePreprocessor $preprocessor,
    ): void {
        $disk = $this->analysis->image_disk;
        $storage = Storage::disk($disk);
        $originalPaths = $this->analysis->original_image_paths ?? [];

        if (empty($originalPaths)) {
            return;
        }

        // Skip if image_paths already differ from originals — already preprocessed (e.g. retry, sync-mode legacy).
        if ($this->analysis->image_paths !== $originalPaths) {
            return;
        }

        $broker = app(AnalysisEventBroker::class);
        $topic = "menu-analysis.{$this->analysis->uuid}";
        $fullPaths = array_map(fn ($p) => $storage->path($p), $originalPaths);

        $broker->publish($topic, 'analysis.preflight-start', [
            'image_count' => count($originalPaths),
        ]);

        $preflights = $preflightService->analyzeMany($fullPaths);

        foreach ($fullPaths as $idx => $fullPath) {
            $result = $preflights[$fullPath] ?? PreflightResult::noop();
            $broker->publish($topic, 'analysis.preflight-image', [
                'index' => $idx,
                'rotation_cw' => $result->rotationCw,
                'content_bbox' => $result->contentBbox,
                'quality' => $result->quality,
            ]);
            $preflightApplier->apply($fullPath, $result);
        }

        Log::channel('llm')->info('Preflight stage complete', [
            'analysis_uuid' => $this->analysis->uuid,
            'image_count' => count($originalPaths),
            'results' => array_map(fn ($r) => [
                'rotation_cw' => $r->rotationCw,
                'has_crop' => $r->contentBbox !== null,
                'quality' => $r->quality,
            ], array_values($preflights)),
        ]);

        $broker->publish($topic, 'analysis.preprocess-start', [
            'image_count' => count($originalPaths),
        ]);

        $preprocessedPaths = [];
        foreach ($originalPaths as $idx => $originalPath) {
            try {
                $fullPath = $storage->path($originalPath);
                $result = $preprocessor->preprocess($fullPath);

                $prepPath = 'menu-analyzer-uploads/prep_'.Str::random(20).'.webp';
                $storage->put($prepPath, file_get_contents($result->path));
                @unlink($result->path);

                $broker->publish($topic, 'analysis.preprocess-image', [
                    'index' => $idx,
                    'original_dims' => $result->meta['original_width'].'x'.$result->meta['original_height'],
                    'final_dims' => $result->meta['final_width'].'x'.$result->meta['final_height'],
                    'final_size_kb' => $result->meta['final_size_kb'],
                ]);

                $preprocessedPaths[] = $prepPath;
            } catch (Throwable $e) {
                Log::channel('llm')->warning('Image preprocess failed, using original', [
                    'analysis_uuid' => $this->analysis->uuid,
                    'path' => $originalPath,
                    'error' => $e->getMessage(),
                ]);
                $preprocessedPaths[] = $originalPath;
            }
        }

        $this->analysis->update([
            'image_paths' => $preprocessedPaths,
        ]);
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
