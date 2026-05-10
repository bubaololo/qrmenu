<?php

namespace App\Jobs;

use App\Models\Menu;
use App\Models\MenuAnalysis;
use App\Services\AnalysisEventBroker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FinalizeAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public MenuAnalysis $analysis)
    {
        $this->onQueue(config('llm.queue', 'llm-analysis'));
    }

    public function handle(): void
    {
        $menu = $this->analysis->result_menu_id
            ? Menu::with('sections.items')->find($this->analysis->result_menu_id)
            : null;

        $itemCount = $menu
            ? $menu->sections->sum(fn ($s) => $s->items->count())
            : 0;

        $this->analysis->markCompleted($menu, menuData: [], itemCount: $itemCount);

        // Preprocessed images served the chunk LLM calls; no longer needed.
        $this->cleanupPreprocessed();

        if ($menu && ! empty($this->analysis->original_image_paths)) {
            CropMenuItemImagesJob::dispatch(
                $menu->id,
                $this->analysis->original_image_paths,
                $this->analysis->image_disk,
                $this->analysis->uuid,
            );
        }

        if ($menu) {
            TranslateMenuJob::dispatchForAllTargetLocales($menu);
        }

        Log::channel('llm')->info('Analysis finalized', [
            'analysis_uuid' => $this->analysis->uuid,
            'menu_id' => $menu?->id,
            'item_count' => $itemCount,
        ]);

        if ($menu) {
            app(AnalysisEventBroker::class)->publish(
                "menu-analysis.{$this->analysis->uuid}",
                'analysis.menu-saved',
                [
                    'menu_id' => $menu->id,
                    'section_count' => $menu->sections->count(),
                    'item_count' => $itemCount,
                ],
            );
        }

        app(AnalysisEventBroker::class)->publish(
            "menu-analysis.{$this->analysis->uuid}",
            'analysis.completed',
            [
                'menu_id' => $menu?->id,
                'restaurant_id' => $this->analysis->restaurant_id,
                'item_count' => $itemCount,
            ],
        );
    }

    private function cleanupPreprocessed(): void
    {
        $disk = Storage::disk($this->analysis->image_disk);
        foreach ($this->analysis->image_paths as $path) {
            $disk->delete($path);
        }
    }
}
