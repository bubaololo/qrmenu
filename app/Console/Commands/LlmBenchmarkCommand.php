<?php

namespace App\Console\Commands;

use App\Models\Prompt;
use App\Support\MenuJson;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use RuntimeException;
use Throwable;

#[Signature('llm:benchmark
    {--only= : Run only one pack (easy|medium|hard)}
    {--model= : Run only one model by key (e.g. gemini-2.5-flash)}
    {--skip= : Comma-separated model keys to skip}
    {--dry-run : Print plan and exit without calling any API}
')]
#[Description('Benchmark all vision LLM providers against the three image test packs')]
class LlmBenchmarkCommand extends Command
{
    private const PACKS = ['easy', 'medium', 'hard'];

    private const IMAGE_SETS_DIR = 'tests/image_test_sets';

    private const PROMPT_TYPE = 'menu_analyzer';

    public function handle(): int
    {
        ini_set('memory_limit', '2G');

        $models = $this->filterModels($this->models(), (string) $this->option('model'), (string) $this->option('skip'));
        $packs = $this->filterPacks((string) $this->option('only'));

        if ($models === []) {
            $this->error('No models selected after filtering.');

            return self::FAILURE;
        }

        $this->line('');
        $this->line('  <info>LLM Benchmark</info>');
        $this->line('');
        $this->line(sprintf('  Models: <info>%d</info>   Packs: <info>%s</info>', count($models), implode(', ', $packs)));

        $packImages = [];
        foreach ($packs as $pack) {
            $imgs = $this->discoverPackImages($pack);
            $packImages[$pack] = $imgs;
            $this->line(sprintf('    %-8s → %d image(s)', $pack, count($imgs)));
        }
        $this->line('');

        if ($this->option('dry-run')) {
            $this->renderPlanTable($models, $packImages);

            return self::SUCCESS;
        }

        $prompt = Prompt::activeForType(self::PROMPT_TYPE);
        if (! $prompt) {
            $this->error('No active prompt for type "'.self::PROMPT_TYPE.'". Run: php artisan db:seed --class=PromptSeeder');

            return self::FAILURE;
        }

        $groundTruths = [];
        foreach ($packs as $pack) {
            $gtPath = base_path(self::IMAGE_SETS_DIR."/{$pack}/ground_truth.json");
            $groundTruths[$pack] = File::exists($gtPath)
                ? MenuJson::decodeMenuFromLlmText(File::get($gtPath))
                : [];
        }

        $runDir = $this->makeRunDir();
        $this->line('  Artifacts → <info>'.$runDir.'</info>');
        $this->line('');

        $rows = [];
        $total = count($models) * count($packs);
        $i = 0;

        foreach ($models as $model) {
            foreach ($packs as $pack) {
                $i++;
                $images = $packImages[$pack];
                $label = sprintf('[%d/%d] %s × %s', $i, $total, $model['key'], $pack);
                $this->line('  '.$label.' ...');

                $result = $this->runOne($model, $images, $prompt);

                $parsed = $result['raw'] !== ''
                    ? MenuJson::decodeMenuFromLlmText($result['raw'])
                    : [];

                $sectionsCount = count($parsed['sections'] ?? []);
                $itemsCount = array_sum(array_map(
                    static fn ($s) => is_array($s['items'] ?? null) ? count($s['items']) : 0,
                    $parsed['sections'] ?? [],
                ));

                $match = $this->computeMatchPercent($parsed, $groundTruths[$pack] ?? []);
                $gtSections = count($groundTruths[$pack]['sections'] ?? []);
                $gtItems = array_sum(array_map(
                    static fn ($s) => is_array($s['items'] ?? null) ? count($s['items']) : 0,
                    $groundTruths[$pack]['sections'] ?? [],
                ));

                $status = match (true) {
                    $result['error'] !== null => 'API_FAIL',
                    $parsed === [] => 'PARSE_FAIL',
                    default => 'OK',
                };

                $meta = [
                    'model_key' => $model['key'],
                    'provider' => $model['provider']->value,
                    'model' => $model['model'],
                    'pack' => $pack,
                    'status' => $status,
                    'duration_ms' => $result['duration_ms'],
                    'input_tokens' => $result['input_tokens'],
                    'output_tokens' => $result['output_tokens'],
                    'finish_reason' => $result['finish_reason'],
                    'sections_count' => $sectionsCount,
                    'items_count' => $itemsCount,
                    'gt_sections' => $gtSections,
                    'gt_items' => $gtItems,
                    'sections_delta' => $gtSections > 0 ? abs($gtSections - $sectionsCount) : null,
                    'items_delta' => $gtItems > 0 ? abs($gtItems - $itemsCount) : null,
                    'match_percent' => $match,
                    'error' => $result['error'],
                ];

                $this->persistArtifacts($runDir, $pack, $model['key'], $result['raw'], $parsed, $meta);

                $rows[] = $meta;

                $this->line(sprintf(
                    '    → <info>%s</info>  %ds  sec=%d  items=%d  match=%s',
                    $status,
                    (int) round($result['duration_ms'] / 1000),
                    $sectionsCount,
                    $itemsCount,
                    $match === null ? '—' : $match.'%',
                ));
            }
        }

        $this->line('');
        $this->renderResultsTable($rows);
        $this->writeSummaryMarkdown($runDir, $rows, $prompt->name);
        $this->writeSummaryCsv($runDir, $rows);

        $this->line('');
        $this->line('  Done. Summary: <info>'.$runDir.'/summary.md</info>');

        return self::SUCCESS;
    }

    /**
     * @return list<array{key:string,provider:Provider,model:string,options:array<string,mixed>,qwen:bool,directHttp:bool,httpUrl:string,httpKey:string,maxTokens:int|null}>
     */
    private function models(): array
    {
        $openaiUrl = 'https://api.openai.com/v1';
        $openaiKey = env('OPENAI_API_KEY', '');
        $qwenUrl = rtrim(env('QWEN_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'), '/');
        $qwenKey = env('QWEN_API_KEY', '');

        return [
            ['key' => 'gemini-2.5-flash',              'provider' => Provider::Gemini,     'model' => 'gemini-2.5-flash',               'options' => ['thinkingBudget' => 0], 'qwen' => false, 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => null],
            ['key' => 'openrouter/gemma-4-26b-free',   'provider' => Provider::OpenRouter, 'model' => 'google/gemma-4-26b-a4b-it:free', 'options' => [], 'qwen' => false, 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => 32768],
            ['key' => 'openrouter/gemma-4-26b',        'provider' => Provider::OpenRouter, 'model' => 'google/gemma-4-26b-a4b-it',      'options' => [], 'qwen' => false, 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => 32768],
            ['key' => 'openrouter/gemma-4-31b-free',   'provider' => Provider::OpenRouter, 'model' => 'google/gemma-4-31b-it:free',     'options' => [], 'qwen' => false, 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => 32768],
            ['key' => 'openrouter/gemma-4-31b',        'provider' => Provider::OpenRouter, 'model' => 'google/gemma-4-31b-it',          'options' => [], 'qwen' => false, 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => 32768],
            ['key' => 'openrouter/qwen-3.6-plus',      'provider' => Provider::OpenRouter, 'model' => 'qwen/qwen3.6-plus',              'options' => [], 'qwen' => false, 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => 32768],
            ['key' => 'openrouter/internvl3-78b',      'provider' => Provider::OpenRouter, 'model' => 'opengvlab/internvl3-78b',        'options' => [], 'qwen' => false, 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => 32768],
            ['key' => 'openrouter/reka-edge',          'provider' => Provider::OpenRouter, 'model' => 'rekaai/reka-edge',               'options' => [], 'qwen' => false, 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => 32768],
            ['key' => 'openrouter/arcee-spotlight',    'provider' => Provider::OpenRouter, 'model' => 'arcee-ai/spotlight',             'options' => [], 'qwen' => false, 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => 32768],
            ['key' => 'openrouter/llama-4-maverick',   'provider' => Provider::OpenRouter, 'model' => 'meta-llama/llama-4-maverick',    'options' => [], 'qwen' => false, 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => 32768],
            ['key' => 'openai/gpt-4.1-2025-04-14',     'provider' => Provider::OpenAI,     'model' => 'gpt-4.1-2025-04-14',             'options' => [], 'qwen' => false, 'directHttp' => true, 'httpUrl' => $openaiUrl, 'httpKey' => $openaiKey, 'maxTokens' => 32768],
            ['key' => 'openai/gpt-4o-2024-08-06',      'provider' => Provider::OpenAI,     'model' => 'gpt-4o-2024-08-06',              'options' => [], 'qwen' => false, 'directHttp' => true, 'httpUrl' => $openaiUrl, 'httpKey' => $openaiKey, 'maxTokens' => 16384],
            ['key' => 'openai/gpt-4-turbo-2024-04-09', 'provider' => Provider::OpenAI,     'model' => 'gpt-4-turbo-2024-04-09',         'options' => [], 'qwen' => false, 'directHttp' => true, 'httpUrl' => $openaiUrl, 'httpKey' => $openaiKey, 'maxTokens' => 4096],
            ['key' => 'dashscope/qwen3-vl-plus',                'provider' => Provider::OpenAI, 'model' => 'qwen3-vl-plus',              'options' => [], 'extraBody' => [],                                                            'qwen' => true, 'directHttp' => true, 'httpUrl' => $qwenUrl, 'httpKey' => $qwenKey, 'maxTokens' => 32768],
            ['key' => 'dashscope/qwen3-vl-plus-2025-12-19',      'provider' => Provider::OpenAI, 'model' => 'qwen3-vl-plus-2025-12-19',   'options' => [], 'extraBody' => [],                                                            'qwen' => true, 'directHttp' => true, 'httpUrl' => $qwenUrl, 'httpKey' => $qwenKey, 'maxTokens' => 32768],
            ['key' => 'dashscope/qwen3-vl-plus-2025-12-19-think', 'provider' => Provider::OpenAI, 'model' => 'qwen3-vl-plus-2025-12-19',   'options' => [], 'extraBody' => ['enable_thinking' => true, 'thinking_budget' => 38912], 'qwen' => true, 'directHttp' => true, 'httpUrl' => $qwenUrl, 'httpKey' => $qwenKey, 'maxTokens' => 32768],
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $models
     * @return list<array<string,mixed>>
     */
    private function filterModels(array $models, string $only, string $skip): array
    {
        $skipList = array_filter(array_map('trim', explode(',', $skip)));
        $models = array_values(array_filter($models, static function ($m) use ($only, $skipList) {
            if ($only !== '' && $m['key'] !== $only) {
                return false;
            }
            if (in_array($m['key'], $skipList, true)) {
                return false;
            }

            return true;
        }));

        return $models;
    }

    /**
     * @return list<string>
     */
    private function filterPacks(string $only): array
    {
        if ($only === '') {
            return self::PACKS;
        }
        if (! in_array($only, self::PACKS, true)) {
            throw new RuntimeException('--only must be one of: '.implode(', ', self::PACKS));
        }

        return [$only];
    }

    /**
     * @return list<string>
     */
    private function discoverPackImages(string $pack): array
    {
        $dir = base_path(self::IMAGE_SETS_DIR."/{$pack}");
        if (! is_dir($dir)) {
            return [];
        }
        $files = glob($dir.'/*.jpg') ?: [];
        $files = array_values(array_filter($files, static fn ($p) => ! str_ends_with($p, 'Zone.Identifier')));
        sort($files);

        return $files;
    }

    /**
     * @param  array<string,mixed>  $model
     * @param  list<string>  $images
     * @return array{raw:string,error:?string,duration_ms:int,input_tokens:?int,output_tokens:?int,finish_reason:?string}
     */
    private function runOne(array $model, array $images, Prompt $prompt): array
    {
        $system = $prompt->system_prompt
            ? [new SystemMessage($prompt->system_prompt)]
            : [];
        $imageObjs = array_map(static fn (string $p) => Image::fromLocalPath($p), $images);
        $user = [new UserMessage($prompt->user_prompt, $imageObjs)];

        $timeout = (int) config('services.openai_compatible.http_timeout_seconds', 3600);

        $startedAt = microtime(true);

        try {
            if ($model['directHttp']) {
                return $this->runViaChatCompletions($model, $images, $prompt, $timeout, $startedAt);
            }

            $builder = Prism::text()
                ->using($model['provider'], $model['model'])
                ->withClientOptions(['timeout' => $timeout])
                ->withSystemPrompts($system)
                ->withMessages($user);

            if ($model['maxTokens'] !== null) {
                $builder = $builder->withMaxTokens($model['maxTokens']);
            }

            if (! empty($model['options'])) {
                $builder = $builder->withProviderOptions($model['options']);
            }

            $response = $builder->asText();

            return [
                'raw' => (string) $response->text,
                'error' => null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'input_tokens' => $response->usage->promptTokens ?? null,
                'output_tokens' => $response->usage->completionTokens ?? null,
                'finish_reason' => $response->finishReason->name ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'raw' => '',
                'error' => $this->shortError($e),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'input_tokens' => null,
                'output_tokens' => null,
                'finish_reason' => null,
            ];
        }
    }

    /**
     * Run a request directly via OpenAI-compatible chat/completions endpoint.
     * Used for OpenAI and DashScope models — Prism routes these through the
     * OpenAI Responses API (/responses) which does not scale to 15 images.
     *
     * @param  array<string,mixed>  $model
     * @param  string[]  $images
     */
    private function runViaChatCompletions(array $model, array $images, Prompt $prompt, int $timeout, float $startedAt): array
    {
        $url = rtrim($model['httpUrl'], '/');
        $apiKey = $model['httpKey'];

        $content = [];
        foreach ($images as $path) {
            $b64 = base64_encode(file_get_contents($path));
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:image/jpeg;base64,'.$b64],
            ];
        }
        $content[] = ['type' => 'text', 'text' => $prompt->user_prompt];

        $messages = [];
        if ($prompt->system_prompt) {
            $messages[] = ['role' => 'system', 'content' => $prompt->system_prompt];
        }
        $messages[] = ['role' => 'user', 'content' => $content];

        try {
            $body = array_merge([
                'model' => $model['model'],
                'messages' => $messages,
                'max_tokens' => $model['maxTokens'] ?? 32768,
            ], $model['extraBody'] ?? []);

            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post("{$url}/chat/completions", $body);

            $data = $response->json();

            if (data_get($data, 'error')) {
                throw new RuntimeException('API Error: '.data_get($data, 'error.message', 'unknown'));
            }

            $text = data_get($data, 'choices.0.message.content', '');
            $finishReason = data_get($data, 'choices.0.finish_reason', null);

            return [
                'raw' => (string) $text,
                'error' => null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'input_tokens' => data_get($data, 'usage.prompt_tokens'),
                'output_tokens' => data_get($data, 'usage.completion_tokens'),
                'finish_reason' => $finishReason,
            ];
        } catch (Throwable $e) {
            return [
                'raw' => '',
                'error' => $this->shortError($e),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'input_tokens' => null,
                'output_tokens' => null,
                'finish_reason' => null,
            ];
        }
    }

    private function shortError(Throwable $e): string
    {
        $msg = $e->getMessage();
        $msg = preg_replace('/\s+/', ' ', $msg) ?? $msg;

        return Str::limit(trim($msg), 500, '');
    }

    /**
     * Count items in parsed whose price.original_text matches any item in ground-truth.
     * Returns null when no ground truth exists.
     *
     * @param  array<string,mixed>  $parsed
     * @param  array<string,mixed>  $gt
     */
    private function computeMatchPercent(array $parsed, array $gt): ?int
    {
        $gtItems = $this->flattenItems($gt);
        if ($gtItems === []) {
            return null;
        }
        $parsedItems = $this->flattenItems($parsed);

        $gtKeys = array_map($this->itemKey(...), $gtItems);
        $parsedKeys = array_map($this->itemKey(...), $parsedItems);
        $parsedSet = array_count_values(array_filter($parsedKeys, static fn ($k) => $k !== ''));

        $hits = 0;
        foreach ($gtKeys as $k) {
            if ($k === '') {
                continue;
            }
            if (! empty($parsedSet[$k])) {
                $hits++;
                $parsedSet[$k]--;
            }
        }

        return (int) round($hits / max(count(array_filter($gtKeys, static fn ($k) => $k !== '')), 1) * 100);
    }

    /**
     * @param  array<string,mixed>  $menu
     * @return list<array<string,mixed>>
     */
    private function flattenItems(array $menu): array
    {
        $out = [];
        foreach ($menu['sections'] ?? [] as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (is_array($item)) {
                    $out[] = $item;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $item
     */
    private function itemKey(array $item): string
    {
        $text = $item['price']['original_text'] ?? '';
        if (! is_string($text)) {
            return '';
        }

        return Str::of($text)
            ->replaceMatches('/\s+/', '')
            ->replaceMatches('/[.,\s]/', '')
            ->lower()
            ->value();
    }

    private function makeRunDir(): string
    {
        $ts = now()->format('Ymd_His');
        $dir = base_path(self::IMAGE_SETS_DIR.'/benchmarks/'.$ts);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * @param  array<string,mixed>  $parsed
     * @param  array<string,mixed>  $meta
     */
    private function persistArtifacts(string $runDir, string $pack, string $modelKey, string $raw, array $parsed, array $meta): void
    {
        $slug = Str::slug(str_replace('/', '-', $modelKey));
        $dir = "{$runDir}/{$pack}__{$slug}";
        File::makeDirectory($dir, 0755, true, true);

        File::put($dir.'/raw.txt', $raw);
        File::put($dir.'/parsed.json', json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        File::put($dir.'/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function renderResultsTable(array $rows): void
    {
        $this->table(
            ['Pack', 'Model', 'Status', 'Time s', 'In tok', 'Out tok', 'Sect', 'Items', 'Match %', 'Error'],
            array_map(static fn ($r) => [
                $r['pack'],
                $r['model_key'],
                $r['status'],
                (int) round($r['duration_ms'] / 1000),
                $r['input_tokens'] ?? '—',
                $r['output_tokens'] ?? '—',
                $r['sections_count'],
                $r['items_count'],
                $r['match_percent'] === null ? '—' : $r['match_percent'].'%',
                Str::limit((string) ($r['error'] ?? ''), 60, ''),
            ], $rows),
        );
    }

    /**
     * @param  list<array<string,mixed>>  $models
     * @param  array<string, list<string>>  $packImages
     */
    private function renderPlanTable(array $models, array $packImages): void
    {
        $this->line('  <comment>Dry run — no API calls will be made.</comment>');
        $this->line('');
        $this->table(
            ['Model key', 'Provider', 'Model'],
            array_map(static fn ($m) => [$m['key'], $m['provider']->value, $m['model']], $models),
        );
        $this->line('');
        foreach ($packImages as $pack => $imgs) {
            $this->line('  <info>'.$pack.'</info>: '.count($imgs).' image(s)');
            foreach ($imgs as $img) {
                $this->line('    - '.basename($img));
            }
        }
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function writeSummaryMarkdown(string $runDir, array $rows, string $promptName): void
    {
        $out = "# LLM Benchmark Summary\n\n";
        $out .= '**Run:** `'.basename($runDir)."`  \n";
        $out .= '**Prompt:** '.$promptName."  \n";
        $out .= '**Generated:** '.now()->toIso8601String()."\n\n";

        $out .= "| Pack | Model | Status | Time s | In tok | Out tok | Sect | Items | Match % | Error |\n";
        $out .= "|---|---|---|---:|---:|---:|---:|---:|---:|---|\n";
        foreach ($rows as $r) {
            $out .= sprintf(
                "| %s | `%s` | %s | %d | %s | %s | %d | %d | %s | %s |\n",
                $r['pack'],
                $r['model_key'],
                $r['status'],
                (int) round($r['duration_ms'] / 1000),
                $r['input_tokens'] ?? '—',
                $r['output_tokens'] ?? '—',
                $r['sections_count'],
                $r['items_count'],
                $r['match_percent'] === null ? '—' : $r['match_percent'].'%',
                str_replace('|', '\\|', Str::limit((string) ($r['error'] ?? ''), 80, '')),
            );
        }

        File::put($runDir.'/summary.md', $out);
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function writeSummaryCsv(string $runDir, array $rows): void
    {
        $fp = fopen($runDir.'/summary.csv', 'w');
        if ($fp === false) {
            return;
        }
        fputcsv($fp, ['pack', 'model_key', 'provider', 'model', 'status', 'duration_ms', 'input_tokens', 'output_tokens', 'sections_count', 'items_count', 'gt_sections', 'gt_items', 'sections_delta', 'items_delta', 'match_percent', 'finish_reason', 'error']);
        foreach ($rows as $r) {
            fputcsv($fp, [
                $r['pack'], $r['model_key'], $r['provider'], $r['model'], $r['status'],
                $r['duration_ms'], $r['input_tokens'], $r['output_tokens'],
                $r['sections_count'], $r['items_count'], $r['gt_sections'], $r['gt_items'],
                $r['sections_delta'], $r['items_delta'], $r['match_percent'],
                $r['finish_reason'], $r['error'],
            ]);
        }
        fclose($fp);
    }
}
