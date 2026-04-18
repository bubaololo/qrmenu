<?php

namespace App\Jobs;

use App\Actions\AnalyzeMenuImageAction;
use App\Actions\SaveMenuAnalysisAction;
use App\Models\Menu;
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

class AnalyzeChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout;

    /**
     * @param  string[]  $chunkPaths  Preprocessed image paths for this chunk
     */
    public function __construct(
        public MenuAnalysis $analysis,
        public array $chunkPaths,
        public int $chunkIndex,
        public int $chunkTotal,
        public int $imageOffset,
    ) {
        $this->timeout = (int) config('llm.chunk_job_timeout', 600);
        $this->onQueue(config('llm.queue', 'llm-analysis'));
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(
        AnalyzeMenuImageAction $action,
        LlmCascadeService $cascade,
        SaveMenuAnalysisAction $saveAction,
    ): void {
        ini_set('memory_limit', '512M');

        if ($this->chunkIndex === 0) {
            $this->analysis->markProcessing();
        }

        $providers = $cascade->resolveProviders(count($this->chunkPaths), $this->analysis->vision_model);
        $built = $action->buildMessages($this->chunkPaths, $this->analysis->image_disk);

        $logContext = [
            'image_count' => count($this->chunkPaths),
            'prompt_id' => $built['prompt']->id,
            'prompt_name' => $built['prompt']->name,
            'paths' => $this->chunkPaths,
            'chunk_index' => $this->chunkIndex,
            'chunk_total' => $this->chunkTotal,
        ];

        $result = $cascade->executeWithFallback($built['messages'], $providers, $this->analysis, $logContext);

        $chunkData = MenuJson::decodeMenuFromLlmText($result['text']);

        if ($this->chunkIndex === 0) {
            if ($this->analysis->restaurant_id === null) {
                throw new \RuntimeException('Chunked analysis requires restaurant_id to persist; refusing to discard chunk 0.');
            }

            // Chunk 0 may contain only cover/header data without items (e.g. restaurant
            // name on a dedicated title page). createMenu() skips the empty-items guard
            // so subsequent chunks can still populate this menu.
            $menu = $saveAction->createMenu(
                $chunkData,
                $this->analysis->restaurant_id,
                $this->analysis->image_count,
            );
            $this->analysis->update(['result_menu_id' => $menu->id]);
        } else {
            $menu = Menu::findOrFail($this->analysis->result_menu_id);
            $saveAction->appendChunk($menu, $chunkData, $this->imageOffset);
        }

        Log::channel('llm')->info('Chunk complete', [
            'analysis_uuid' => $this->analysis->uuid,
            'chunk_index' => $this->chunkIndex + 1,
            'chunk_total' => $this->chunkTotal,
            'menu_id' => $this->analysis->result_menu_id,
            'provider' => $result['provider'].':'.$result['model'],
            'tier' => $result['tier'],
        ]);
    }

    public function failed(Throwable $e): void
    {
        $this->analysis->markFailed(sprintf(
            'Chunk %d/%d failed after %d attempts: %s',
            $this->chunkIndex + 1,
            $this->chunkTotal,
            $this->tries,
            $e->getMessage(),
        ));

        // Chain breaks on failure; FinalizeAnalysisJob won't run, so clean up images here.
        $disk = Storage::disk($this->analysis->image_disk);
        foreach ($this->analysis->image_paths as $path) {
            $disk->delete($path);
        }
        foreach ($this->analysis->original_image_paths ?? [] as $path) {
            $disk->delete($path);
        }

        Log::channel('llm')->error('Chunk exhausted retries', [
            'analysis_uuid' => $this->analysis->uuid,
            'chunk_index' => $this->chunkIndex + 1,
            'chunk_total' => $this->chunkTotal,
            'error' => $e->getMessage(),
        ]);
    }
}
