<?php

namespace App\Jobs;

use App\Llm\DeepSeekTextProvider;
use App\Llm\OpenRouterProvider;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Prompt;
use App\Models\Restaurant;
use App\Services\LlmCascadeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Matriphe\ISO639\ISO639;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

/**
 * Translates a single entity field into the given target locales.
 *
 * Triggered by TranslationObserver when an initial translation is created
 * or its value changes. Reuses the same TSV dialect and LLM cascade as
 * TranslateChunkJob, but operates on one row and writes back only the
 * requested field — soothat manual edits to sibling translations are
 * preserved.
 */
class TranslateEntityJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * If the source entity is deleted between dispatch and execution (e.g. the
     * user adds a category/item, then removes it before the translation runs),
     * silently discard the job instead of failing with ModelNotFoundException.
     */
    public bool $deleteWhenMissingModels = true;

    private const PROMPT_TYPE = 'menu_translator';

    /**
     * @param  list<string>  $targetLocales
     */
    public function __construct(
        public Model $entity,
        public array $targetLocales,
        public string $field,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(LlmCascadeService $cascade): void
    {
        if (empty($this->targetLocales)) {
            return;
        }

        $prompt = Prompt::activeForType(self::PROMPT_TYPE);
        if (! $prompt) {
            Log::channel('llm')->warning('Entity translation aborted: no active prompt', [
                'entity_type' => $this->entity->getMorphClass(),
                'entity_id' => $this->entity->getKey(),
                'field' => $this->field,
            ]);

            return;
        }

        $tsvLine = $this->buildTsvLine();
        if ($tsvLine === null) {
            return;
        }

        $menu = $this->resolveMenu();
        $restaurant = $menu?->restaurant;
        $sourceLocale = $menu?->source_locale ?? $restaurant?->primary_language;

        if ($sourceLocale === null) {
            Log::channel('llm')->warning('Entity translation aborted: no source locale', [
                'entity_type' => $this->entity->getMorphClass(),
                'entity_id' => $this->entity->getKey(),
            ]);

            return;
        }

        $iso = new ISO639;
        $sourceDescription = ($iso->languageByCode1($sourceLocale) ?: $sourceLocale)." ({$sourceLocale})";

        foreach ($this->targetLocales as $targetLocale) {
            $this->translateInto(
                $cascade,
                $prompt,
                $tsvLine,
                $targetLocale,
                $sourceDescription,
                $sourceLocale,
                $restaurant,
                $iso,
            );
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('llm')->error('Entity translation exhausted retries', [
            'entity_type' => $this->entity->getMorphClass(),
            'entity_id' => $this->entity->getKey(),
            'field' => $this->field,
            'target_locales' => $this->targetLocales,
            'error' => $e->getMessage(),
        ]);
    }

    private function translateInto(
        LlmCascadeService $cascade,
        Prompt $prompt,
        string $tsvLine,
        string $targetLocale,
        string $sourceDescription,
        string $sourceLocale,
        ?Restaurant $restaurant,
        ISO639 $iso,
    ): void {
        $targetLocaleName = $iso->languageByCode1($targetLocale) ?: $targetLocale;

        $userPrompt = str_replace(
            ['{target_locale}', '{source_locale}', '{restaurant_name}', '{address}'],
            [
                "{$targetLocaleName} ({$targetLocale})",
                $sourceDescription,
                $restaurant?->name ?? '',
                $restaurant?->address ?? '',
            ],
            $prompt->user_prompt,
        );

        $messages = [
            new SystemMessage($prompt->system_prompt),
            new UserMessage($userPrompt."\n\n".$tsvLine),
        ];

        // Short timeout so a slow deepseek fails over to openrouter in seconds.
        $timeout = (int) config('llm.translation.http_timeout_seconds');
        $providers = [
            app()->makeWith(DeepSeekTextProvider::class, ['timeout' => $timeout]),
            app()->makeWith(OpenRouterProvider::class, [
                'openRouterModel' => (string) config('llm.translation.openrouter_fallback_model', 'openai/gpt-4.1-mini'),
                'timeout' => $timeout,
            ]),
        ];

        $logContext = [
            'entity_type' => $this->entity->getMorphClass(),
            'entity_id' => $this->entity->getKey(),
            'field' => $this->field,
            'target_locale' => $targetLocale,
            'source_locale' => $sourceLocale,
        ];

        $result = $cascade->executeWithFallback($messages, $providers, null, $logContext);

        $this->parseAndWrite($result['text'], $targetLocale);

        Log::channel('llm')->info('Entity translation complete', $logContext + [
            'provider' => $result['provider'].':'.$result['model'],
            'tier' => $result['tier'],
        ]);
    }

    private function buildTsvLine(): ?string
    {
        $entity = $this->entity;

        return match (true) {
            $entity instanceof MenuSection => sprintf(
                'S|%d|%s',
                $entity->getKey(),
                (string) $entity->initialText('name'),
            ),
            $entity instanceof MenuItem => sprintf(
                'I|%d|%s|%s',
                $entity->getKey(),
                (string) $entity->initialText('name'),
                (string) $entity->initialText('description'),
            ),
            $entity instanceof MenuOptionGroup => sprintf(
                '%s|%d|%s',
                $entity->is_variation ? 'V' : 'G',
                $entity->getKey(),
                (string) $entity->initialText('name'),
            ),
            $entity instanceof MenuOptionGroupOption => sprintf(
                'O|%d|%s',
                $entity->getKey(),
                (string) $entity->initialText('name'),
            ),
            default => null,
        };
    }

    /**
     * Parse LLM TSV output and write back ONLY the requested field for the
     * matching entity. Sibling fields (e.g. description when we're translating
     * name) are ignored to preserve manual corrections in other locales.
     */
    private function parseAndWrite(string $raw, string $locale): void
    {
        $text = trim($raw);
        $text = preg_replace('/^```[a-z]*\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```\s*$/', '', $text) ?? $text;

        foreach (explode("\n", trim($text)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode('|', $line);
            $type = $parts[0] ?? '';
            $expectedId = (int) $this->entity->getKey();

            switch ($type) {
                case 'S':
                case 'V':
                case 'G':
                case 'O':
                    if ($this->field !== 'name') {
                        break;
                    }
                    if ((int) ($parts[1] ?? 0) !== $expectedId) {
                        break;
                    }
                    $name = $parts[2] ?? '';
                    if ($name !== '') {
                        $this->entity->setTranslation('name', $locale, $name, isInitial: false);
                    }
                    break;

                case 'I':
                    if ((int) ($parts[1] ?? 0) !== $expectedId) {
                        break;
                    }
                    if ($this->field === 'name') {
                        $name = $parts[2] ?? '';
                        if ($name !== '') {
                            $this->entity->setTranslation('name', $locale, $name, isInitial: false);
                        }
                    } elseif ($this->field === 'description') {
                        $desc = $parts[3] ?? '';
                        if ($desc !== '') {
                            $this->entity->setTranslation('description', $locale, $desc, isInitial: false);
                        }
                    }
                    break;
            }
        }
    }

    private function resolveMenu(): ?Menu
    {
        return match (true) {
            $this->entity instanceof MenuItem => $this->entity->section?->menu,
            $this->entity instanceof MenuSection => $this->entity->menu,
            $this->entity instanceof MenuOptionGroup => $this->entity->section?->menu,
            $this->entity instanceof MenuOptionGroupOption => $this->entity->group?->section?->menu,
            default => null,
        };
    }
}
