<?php

namespace App\Jobs;

use App\Models\Menu;
use App\Models\Prompt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Matriphe\ISO639\ISO639;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

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
        $this->menu->load([
            'restaurant',
            'sections.initialTranslations',
            'sections.items.initialTranslations',
            'sections.optionGroups.initialTranslations',
            'sections.optionGroups.options.initialTranslations',
        ]);

        $restaurant = $this->menu->restaurant;
        $sourceLocale = $this->menu->source_locale ?? $restaurant->primary_language ?? 'und';

        $iso = new ISO639;
        $targetLocaleName = $iso->languageByCode1($this->targetLocale) ?: $this->targetLocale;
        $sourceLocaleName = $iso->languageByCode1($sourceLocale) ?: $sourceLocale;

        $prompt = Prompt::activeForType(self::PROMPT_TYPE);

        if (! $prompt) {
            Log::channel('llm')->warning('Translation aborted: no active prompt', ['type' => self::PROMPT_TYPE]);

            return;
        }

        $llm = Log::channel('llm');
        $totalCount = 0;

        // Build compact TSV payload for the entire menu and send in one request
        [$tsvPayload, $idMap] = $this->buildTsvPayload();

        $userPrompt = str_replace(
            ['{target_locale}', '{source_locale}', '{restaurant_name}', '{city}', '{country}'],
            ["{$targetLocaleName} ({$this->targetLocale})", "{$sourceLocaleName} ({$sourceLocale})", $restaurant->name ?? '', $restaurant->city ?? '', $restaurant->country ?? ''],
            $prompt->user_prompt,
        );

        $fullUserMessage = $userPrompt."\n\n".$tsvPayload;

        $llm->info('Translation request', [
            'menu_id' => $this->menu->id,
            'target_locale' => $this->targetLocale,
            'source_locale' => $sourceLocale,
            'items_total' => $idMap['items_count'],
            'payload_size' => strlen($tsvPayload),
            'user_prompt' => $fullUserMessage,
        ]);

        $startedAt = microtime(true);

        try {
            $response = Prism::text()
                ->using(Provider::DeepSeek, 'deepseek-chat')
                ->withClientOptions(['timeout' => 120])
                ->withMessages([
                    new SystemMessage($prompt->system_prompt),
                    new UserMessage($fullUserMessage),
                ])
                ->asText();
        } catch (\Throwable $e) {
            $llm->error('Translation LLM failed', [
                'menu_id' => $this->menu->id,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $llm->info('Translation response', [
            'menu_id' => $this->menu->id,
            'target_locale' => $this->targetLocale,
            'duration_ms' => $durationMs,
            'tokens' => ($response->usage->promptTokens ?? 0) + ($response->usage->completionTokens ?? 0),
            'finish_reason' => $response->finishReason->name ?? 'unknown',
            'response_text' => $response->text,
        ]);

        $totalCount = $this->parseTsvAndSave($response->text, $idMap);

        $llm->info('Translation complete', [
            'menu_id' => $this->menu->id,
            'locale' => $this->targetLocale,
            'fields_count' => $totalCount,
        ]);
    }

    /**
     * Build compact TSV payload. Format:
     *
     * S|section_id|Section Name
     * I|item_id|Item Name|Item Description
     * V|group_id|Group Name  (variation group at section level)
     * G|group_id|Group Name  (option group at section level)
     * O|option_id|Option Name
     * R|field|value
     *
     * Groups are at section level — no per-item duplication.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildTsvPayload(): array
    {
        $lines = [];
        $idMap = [
            'sections' => [],   // id => section model
            'items' => [],      // id => item model
            'groups' => [],     // id => group model (both V and G)
            'options' => [],    // id => option model
            'items_count' => 0,
        ];

        foreach ($this->menu->sections as $section) {
            $sectionName = $section->initialText('name') ?? '';
            $lines[] = "S|{$section->id}|{$sectionName}";
            $idMap['sections'][$section->id] = $section;

            foreach ($section->items as $item) {
                $name = $item->initialText('name') ?? '';
                $desc = $item->initialText('description') ?? '';
                $lines[] = "I|{$item->id}|{$name}|{$desc}";
                $idMap['items'][$item->id] = $item;
                $idMap['items_count']++;
            }

            foreach ($section->optionGroups as $group) {
                $groupName = $group->initialText('name') ?? '';
                $type = $group->is_variation ? 'V' : 'G';
                $lines[] = "{$type}|{$group->id}|{$groupName}";
                $idMap['groups'][$group->id] = $group;

                foreach ($group->options as $opt) {
                    $optName = $opt->initialText('name') ?? '';
                    $lines[] = "O|{$opt->id}|{$optName}";
                    $idMap['options'][$opt->id] = $opt;
                }
            }
        }

        // Restaurant
        $restName = $this->menu->restaurant->initialText('name') ?? '';
        $restAddr = $this->menu->restaurant->initialText('address') ?? '';
        $lines[] = "R|name|{$restName}";
        if ($restAddr !== '') {
            $lines[] = "R|address|{$restAddr}";
        }

        return [implode("\n", $lines), $idMap];
    }

    private function parseTsvAndSave(string $raw, array $idMap): int
    {
        // Strip markdown fences
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
