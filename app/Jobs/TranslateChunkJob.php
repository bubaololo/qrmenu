<?php

namespace App\Jobs;

use App\Llm\DeepSeekTextProvider;
use App\Llm\OpenRouterProvider;
use App\Models\Menu;
use App\Models\Prompt;
use App\Services\LlmCascadeService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Matriphe\ISO639\ISO639;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class TranslateChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    private const PROMPT_TYPE = 'menu_translator';

    /**
     * @param  list<string>  $chunkLines  TSV lines for this chunk (S|, I|, V|, G|, O|, R| entries)
     */
    public function __construct(
        public Menu $menu,
        public string $targetLocale,
        public array $chunkLines,
        public int $chunkIndex,
        public int $chunkTotal,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(LlmCascadeService $cascade): void
    {
        $prompt = Prompt::activeForType(self::PROMPT_TYPE);
        if (! $prompt) {
            Log::channel('llm')->warning('Translation chunk aborted: no active prompt', [
                'menu_id' => $this->menu->id,
                'target_locale' => $this->targetLocale,
                'chunk_index' => $this->chunkIndex + 1,
                'chunk_total' => $this->chunkTotal,
            ]);

            return;
        }

        $this->menu->load([
            'restaurant',
            'sections.items',
            'sections.optionGroups.options',
        ]);

        $idMap = $this->buildIdMap();

        $restaurant = $this->menu->restaurant;
        $sourceLocale = $this->menu->source_locale ?? $restaurant->primary_language ?? 'und';

        $iso = new ISO639;
        $targetLocaleName = $iso->languageByCode1($this->targetLocale) ?: $this->targetLocale;
        $sourceLocaleName = $iso->languageByCode1($sourceLocale) ?: $sourceLocale;

        $userPrompt = str_replace(
            ['{target_locale}', '{source_locale}', '{restaurant_name}', '{city}', '{country}'],
            ["{$targetLocaleName} ({$this->targetLocale})", "{$sourceLocaleName} ({$sourceLocale})", $restaurant->name ?? '', $restaurant->city ?? '', $restaurant->country ?? ''],
            $prompt->user_prompt,
        );

        $chunkTsv = implode("\n", $this->chunkLines);
        $fullUserMessage = $userPrompt."\n\n".$chunkTsv;

        $messages = [
            new SystemMessage($prompt->system_prompt),
            new UserMessage($fullUserMessage),
        ];

        $providers = [
            app(DeepSeekTextProvider::class),
            app()->makeWith(OpenRouterProvider::class, [
                'openRouterModel' => (string) config('llm.translation.openrouter_fallback_model', 'openai/gpt-4.1-mini'),
            ]),
        ];

        $logContext = [
            'menu_id' => $this->menu->id,
            'target_locale' => $this->targetLocale,
            'source_locale' => $sourceLocale,
            'chunk_index' => $this->chunkIndex + 1,
            'chunk_total' => $this->chunkTotal,
            'chunk_lines' => count($this->chunkLines),
            'payload_size' => strlen($chunkTsv),
        ];

        $result = $cascade->executeWithFallback($messages, $providers, null, $logContext);

        $count = $this->parseTsvAndSave($result['text'], $idMap);

        Log::channel('llm')->info('Translation chunk complete', [
            'menu_id' => $this->menu->id,
            'target_locale' => $this->targetLocale,
            'chunk_index' => $this->chunkIndex + 1,
            'chunk_total' => $this->chunkTotal,
            'fields_written' => $count,
            'provider' => $result['provider'].':'.$result['model'],
            'tier' => $result['tier'],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('llm')->error('Translation chunk exhausted retries', [
            'menu_id' => $this->menu->id,
            'target_locale' => $this->targetLocale,
            'chunk_index' => $this->chunkIndex + 1,
            'chunk_total' => $this->chunkTotal,
            'error' => $e->getMessage(),
        ]);
    }

    /** @return array<string, array<int, Model>> */
    private function buildIdMap(): array
    {
        $map = ['sections' => [], 'items' => [], 'groups' => [], 'options' => []];

        foreach ($this->menu->sections as $section) {
            $map['sections'][$section->id] = $section;
            foreach ($section->items as $item) {
                $map['items'][$item->id] = $item;
            }
            foreach ($section->optionGroups as $group) {
                $map['groups'][$group->id] = $group;
                foreach ($group->options as $opt) {
                    $map['options'][$opt->id] = $opt;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<string, array<int, Model>>  $idMap
     */
    private function parseTsvAndSave(string $raw, array $idMap): int
    {
        $text = trim($raw);
        $text = preg_replace('/^```[a-z]*\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```\s*$/', '', $text) ?? $text;

        $count = 0;
        $restaurant = $this->menu->restaurant;

        foreach (explode("\n", trim($text)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode('|', $line);
            $type = $parts[0] ?? '';

            switch ($type) {
                case 'S':
                    $id = (int) ($parts[1] ?? 0);
                    $name = $parts[2] ?? '';
                    $section = $idMap['sections'][$id] ?? null;
                    if ($section && $name !== '') {
                        $section->setTranslation('name', $this->targetLocale, $name, isInitial: false);
                        $count++;
                    }
                    break;

                case 'I':
                    $id = (int) ($parts[1] ?? 0);
                    $name = $parts[2] ?? '';
                    $desc = $parts[3] ?? '';
                    $item = $idMap['items'][$id] ?? null;
                    if ($item && $name !== '') {
                        $item->setTranslation('name', $this->targetLocale, $name, isInitial: false);
                        $count++;
                    }
                    if ($item && $desc !== '') {
                        $item->setTranslation('description', $this->targetLocale, $desc, isInitial: false);
                        $count++;
                    }
                    break;

                case 'V':
                case 'G':
                    $id = (int) ($parts[1] ?? 0);
                    $name = $parts[2] ?? '';
                    $group = $idMap['groups'][$id] ?? null;
                    if ($group && $name !== '') {
                        $group->setTranslation('name', $this->targetLocale, $name, isInitial: false);
                        $count++;
                    }
                    break;

                case 'O':
                    $id = (int) ($parts[1] ?? 0);
                    $name = $parts[2] ?? '';
                    $opt = $idMap['options'][$id] ?? null;
                    if ($opt && $name !== '') {
                        $opt->setTranslation('name', $this->targetLocale, $name, isInitial: false);
                        $count++;
                    }
                    break;

                case 'R':
                    $field = $parts[1] ?? '';
                    $value = $parts[2] ?? '';
                    if (in_array($field, ['name', 'address']) && $value !== '') {
                        $restaurant->setTranslation($field, $this->targetLocale, $value, isInitial: false);
                        $count++;
                    }
                    break;
            }
        }

        return $count;
    }
}
