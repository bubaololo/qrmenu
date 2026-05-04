<?php

namespace App\Jobs;

use App\Actions\AnalyzeMenuImageAction;
use App\Actions\SaveMenuAnalysisAction;
use App\Models\Menu;
use App\Models\MenuAnalysis;
use App\Services\AnalysisEventBroker;
use App\Services\LlmCascadeService;
use App\Support\MenuJson;
use Illuminate\Bus\Batchable;
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
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

        // Bus::batch cancels remaining jobs once any sibling has failed — skip quietly.
        // Also bail out if the batch already finished (then() fired) — a stale retry
        // landing here would only crash on missing prep files cleaned up by FinalizeAnalysisJob.
        if ($this->batch() && ($this->batch()->cancelled() || $this->batch()->finished())) {
            Log::channel('llm')->info('Chunk skipped (batch already finished/cancelled)', [
                'analysis_uuid' => $this->analysis->uuid,
                'chunk_index' => $this->chunkIndex,
                'attempts' => $this->attempts(),
            ]);

            return;
        }

        // Defensive: if the analysis is already in a terminal state, drop the retry.
        // Happens when an earlier attempt of this same chunk succeeded and FinalizeAnalysisJob
        // already activated the menu + cleaned up the preprocessed images.
        $this->analysis->refresh();
        if (in_array($this->analysis->status->value, ['completed', 'failed'], true)) {
            Log::channel('llm')->info('Chunk skipped (analysis already terminal)', [
                'analysis_uuid' => $this->analysis->uuid,
                'chunk_index' => $this->chunkIndex,
                'analysis_status' => $this->analysis->status->value,
                'attempts' => $this->attempts(),
            ]);

            return;
        }

        if ($this->analysis->result_menu_id === null) {
            throw new \RuntimeException('Chunked analysis requires result_menu_id set by orchestrator; refusing to run.');
        }

        // Defensive: skip silently if the preprocessed images are already gone — we lost the race
        // to FinalizeAnalysisJob's cleanup. The batch is otherwise considered complete.
        $disk = Storage::disk($this->analysis->image_disk);
        foreach ($this->chunkPaths as $path) {
            if (! $disk->exists($path)) {
                Log::channel('llm')->info('Chunk skipped (prep file already cleaned up)', [
                    'analysis_uuid' => $this->analysis->uuid,
                    'chunk_index' => $this->chunkIndex,
                    'missing_path' => $path,
                    'attempts' => $this->attempts(),
                ]);

                return;
            }
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

        $menu = Menu::findOrFail($this->analysis->result_menu_id);
        $saveAction->appendChunk($menu, $chunkData, $this->imageOffset);

        Log::channel('llm')->info('Chunk complete', [
            'analysis_uuid' => $this->analysis->uuid,
            'chunk_index' => $this->chunkIndex + 1,
            'chunk_total' => $this->chunkTotal,
            'menu_id' => $this->analysis->result_menu_id,
            'provider' => $result['provider'].':'.$result['model'],
            'tier' => $result['tier'],
        ]);

        app(AnalysisEventBroker::class)->publish(
            "menu-analysis.{$this->analysis->uuid}",
            'analysis.chunk-complete',
            [
                'chunk_index' => $this->chunkIndex + 1,
                'chunk_total' => $this->chunkTotal,
                'provider' => $result['provider'].':'.$result['model'],
                'tier' => $result['tier'],
            ],
        );
    }

    public function failed(Throwable $e): void
    {
        // Analysis-level status + image cleanup happen in the Bus::batch catch() hook
        // so they're not duplicated if multiple chunks fail concurrently.
        Log::channel('llm')->error('Chunk exhausted retries', [
            'analysis_uuid' => $this->analysis->uuid,
            'chunk_index' => $this->chunkIndex + 1,
            'chunk_total' => $this->chunkTotal,
            'error' => $e->getMessage(),
        ]);
    }
}
