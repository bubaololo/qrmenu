<?php

namespace App\Jobs;

use App\Enums\OptionGroupKind;
use App\Models\Menu;
use App\Models\Prompt;
use App\Services\AnalysisEventBroker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class TranslateMenuJob implements ShouldQueue
{
    use Queueable;

    private const PROMPT_TYPE = 'menu_translator';

    public function __construct(
        public Menu $menu,
        public string $targetLocale,
    ) {}

    /**
     * Dispatch one TranslateMenuJob per non-source target locale known to the
     * menu. Used as the post-bulk-save replacement for the per-entity observer
     * path (TranslationObserver / TranslateEntityJob), which is silenced via
     * Translation::withoutEvents() during bulk writes.
     *
     * @return list<string> the locales actually dispatched
     */
    public static function dispatchForAllTargetLocales(Menu $menu): array
    {
        $dispatched = [];
        foreach ($menu->availableLocales() as $loc) {
            if ($loc['is_source']) {
                continue;
            }
            self::dispatch($menu, $loc['code']);
            $dispatched[] = $loc['code'];
        }

        return $dispatched;
    }

    public function handle(): void
    {
        $prompt = Prompt::activeForType(self::PROMPT_TYPE);

        if (! $prompt) {
            Log::channel('llm')->warning('Translation aborted: no active prompt', [
                'type' => self::PROMPT_TYPE,
                'menu_id' => $this->menu->id,
                'target_locale' => $this->targetLocale,
            ]);

            return;
        }

        $this->menu->load([
            'sections.initialTranslations',
            'sections.items.initialTranslations',
            'optionGroups.initialTranslations',
            'optionGroups.options.initialTranslations',
            'restaurant',
        ]);

        $lines = $this->buildTsvLines();

        $chunkLines = (int) config('llm.translation.chunk_lines', 80);
        $chunks = array_chunk($lines, $chunkLines);
        $chunkTotal = count($chunks);

        Log::channel('llm')->info('Translation start', [
            'menu_id' => $this->menu->id,
            'target_locale' => $this->targetLocale,
            'lines_total' => count($lines),
            'chunk_total' => $chunkTotal,
            'chunk_lines' => $chunkLines,
        ]);

        if ($chunkTotal === 0) {
            return;
        }

        app(AnalysisEventBroker::class)->publish(
            "menu-translation.{$this->menu->id}.{$this->targetLocale}",
            'translation.started',
            [
                'menu_id' => $this->menu->id,
                'target_locale' => $this->targetLocale,
                'chunk_total' => $chunkTotal,
                'lines_total' => count($lines),
            ],
        );

        // Mirror to the restaurant admin channel so a toast fires regardless of
        // which screen the admin is on (the per-locale topic above drives the
        // inline progress UI).
        app(AnalysisEventBroker::class)->publish(
            "restaurant.{$this->menu->restaurant_id}",
            'translation.started',
            [
                'menu_id' => $this->menu->id,
                'locale' => $this->targetLocale,
                'restaurant_id' => $this->menu->restaurant_id,
            ],
        );

        $jobs = [];
        foreach ($chunks as $i => $chunk) {
            $jobs[] = new TranslateChunkJob(
                $this->menu,
                $this->targetLocale,
                $chunk,
                $i,
                $chunkTotal,
            );
        }

        $menuId = $this->menu->id;
        $locale = $this->targetLocale;
        $restaurantId = $this->menu->restaurant_id;

        Bus::batch($jobs)
            ->name("menu-translation-{$menuId}-{$locale}")
            ->onQueue(config('llm.queue', 'llm-analysis'))
            ->finally(function ($batch) use ($menuId, $locale, $restaurantId) {
                Log::channel('llm')->info('Translation batch complete', [
                    'menu_id' => $menuId,
                    'target_locale' => $locale,
                    'chunks_total' => $batch->totalJobs,
                    'chunks_failed' => $batch->failedJobs,
                    'chunks_ok' => $batch->totalJobs - $batch->failedJobs,
                ]);

                app(AnalysisEventBroker::class)->publish(
                    "menu-translation.{$menuId}.{$locale}",
                    'translation.completed',
                    [
                        'menu_id' => $menuId,
                        'target_locale' => $locale,
                        'chunks_total' => $batch->totalJobs,
                        'chunks_failed' => $batch->failedJobs,
                        'chunks_ok' => $batch->totalJobs - $batch->failedJobs,
                    ],
                );

                app(AnalysisEventBroker::class)->publish(
                    "restaurant.{$restaurantId}",
                    'translation.completed',
                    [
                        'menu_id' => $menuId,
                        'locale' => $locale,
                        'restaurant_id' => $restaurantId,
                        'chunks_failed' => $batch->failedJobs,
                    ],
                );
            })
            ->dispatch();
    }

    /**
     * Build compact TSV lines to translate. Format:
     *
     * S|section_id|Section Name
     * I|item_id|Item Name|Item Description
     * V|group_id|Group Name  (variation group at section level)
     * G|group_id|Group Name  (option group at section level)
     * O|option_id|Option Name
     * R|field|value
     *
     * @return list<string>
     */
    private function buildTsvLines(): array
    {
        $lines = [];

        foreach ($this->menu->sections as $section) {
            $sectionName = $section->initialText('name') ?? '';
            $lines[] = "S|{$section->id}|{$sectionName}";

            foreach ($section->items as $item) {
                $name = $item->initialText('name') ?? '';
                $desc = $item->initialText('description') ?? '';
                $lines[] = "I|{$item->id}|{$name}|{$desc}";
            }
        }

        // Option groups are shared across the whole menu, so emit them once.
        foreach ($this->menu->optionGroups as $group) {
            $groupName = $group->initialText('name') ?? '';
            $type = $group->kind === OptionGroupKind::Variant ? 'V' : 'G';
            $lines[] = "{$type}|{$group->id}|{$groupName}";

            foreach ($group->options as $opt) {
                $optName = $opt->initialText('name') ?? '';
                $lines[] = "O|{$opt->id}|{$optName}";
            }
        }

        return $lines;
    }
}
