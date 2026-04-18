<?php

namespace App\Jobs;

use App\Models\Menu;
use App\Models\Prompt;
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
            'sections.optionGroups.initialTranslations',
            'sections.optionGroups.options.initialTranslations',
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

        Bus::chain($jobs)->dispatch();
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

            foreach ($section->optionGroups as $group) {
                $groupName = $group->initialText('name') ?? '';
                $type = $group->is_variation ? 'V' : 'G';
                $lines[] = "{$type}|{$group->id}|{$groupName}";

                foreach ($group->options as $opt) {
                    $optName = $opt->initialText('name') ?? '';
                    $lines[] = "O|{$opt->id}|{$optName}";
                }
            }
        }

        $restName = $this->menu->restaurant->initialText('name') ?? '';
        $restAddr = $this->menu->restaurant->initialText('address') ?? '';
        $lines[] = "R|name|{$restName}";
        if ($restAddr !== '') {
            $lines[] = "R|address|{$restAddr}";
        }

        return $lines;
    }
}
