<?php

namespace App\Console\Commands;

use App\Models\Prompt;
use App\Services\ImagePreflightApplier;
use App\Services\ImagePreflightService;
use App\Services\ImagePreprocessor;
use App\Support\MenuJson;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Imagick;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use RuntimeException;
use Throwable;

#[Signature('llm:bbox-test
    {--image=tests/image_test_sets/crop_test/20260417_152140.jpg : Source image path (relative to project root)}
    {--model= : Run only one model by key}
    {--skip= : Comma-separated model keys to skip}
    {--output=storage/app/public/bbox_test : Output dir for crops (relative to project root)}
    {--max-dim=1600 : Max dimension for the image sent to LLMs (smaller = faster, less accurate bbox)}
')]
#[Description('Test bbox detection accuracy across vision LLMs by visually inspecting crops')]
class BboxTestCommand extends Command
{
    private const PROMPT_TYPE = 'menu_analyzer';

    public function handle(
        ImagePreflightService $preflight,
        ImagePreflightApplier $applier,
        ImagePreprocessor $preprocessor,
    ): int {
        ini_set('memory_limit', '2G');

        $imagePath = base_path((string) $this->option('image'));
        if (! File::exists($imagePath)) {
            $this->error('Image not found: '.$imagePath);

            return self::FAILURE;
        }

        $prompt = Prompt::activeForType(self::PROMPT_TYPE);
        if (! $prompt) {
            $this->error('No active prompt. Run: php artisan db:seed --class=PromptSeeder');

            return self::FAILURE;
        }

        $models = $this->filterModels($this->models());
        if ($models === []) {
            $this->error('No models selected.');

            return self::FAILURE;
        }

        $outputRoot = base_path((string) $this->option('output'));
        File::ensureDirectoryExists($outputRoot);

        $this->line('');
        $this->line('  <info>Bbox test</info>');
        $this->line('  Image:  '.basename($imagePath));
        $this->line('  Models: '.count($models));
        $this->line('  Output: '.$outputRoot);
        $this->line('');

        // Prepare image: preflight + preprocess, ONCE. All models see the same input.
        $workDir = sys_get_temp_dir().'/bbox_test_'.uniqid();
        File::ensureDirectoryExists($workDir);

        $adjusted = $workDir.'/adjusted.jpg';
        copy($imagePath, $adjusted);

        $this->line('  → preflight…');
        $pf = $preflight->analyze($adjusted);
        $applier->apply($adjusted, $pf);
        $this->line('    rotation_cw='.$pf->rotationCw.' bbox='.json_encode($pf->contentBbox).' quality='.$pf->quality);

        $this->line('  → preprocess…');
        $prep = $preprocessor->preprocess($adjusted);
        $preprocessedPath = $workDir.'/preprocessed.webp';
        copy($prep->path, $preprocessedPath);
        @unlink($prep->path);
        $this->line('    final='.$prep->meta['final_width'].'x'.$prep->meta['final_height'].' size='.$prep->meta['final_size_kb'].'KB');

        // Convert to JPEG + resize to max-dim for LLM APIs (wider compatibility + avoids
        // huge payloads that some providers silently drop).
        $jpegPath = $workDir.'/preprocessed.jpg';
        $maxDim = (int) $this->option('max-dim');
        $img = new Imagick($preprocessedPath);
        if (max($img->getImageWidth(), $img->getImageHeight()) > $maxDim) {
            $img->resizeImage($maxDim, $maxDim, Imagick::FILTER_LANCZOS, 1, true);
        }
        $img->setImageFormat('jpeg');
        $img->setImageCompressionQuality(82);
        $img->writeImage($jpegPath);
        $jw = $img->getImageWidth();
        $jh = $img->getImageHeight();
        $img->clear();
        $img->destroy();
        $this->line('    jpeg='.$jw.'x'.$jh.' '.(int) round(filesize($jpegPath) / 1024).'KB');
        $this->line('');

        // Reference copy for visual comparison
        copy($preprocessedPath, $outputRoot.'/_input.webp');

        $summary = [];
        foreach ($models as $model) {
            $key = $model['key'];
            $this->line('  <comment>'.$key.'</comment>');

            $result = $this->runModel($model, $jpegPath, $prompt);

            if ($result['error'] !== null) {
                $this->line('    <error>'.$result['error'].'</error>');
                $summary[$key] = ['items' => 0, 'bboxes' => 0, 'crops' => 0, 'error' => $result['error'], 'duration_ms' => $result['duration_ms']];

                continue;
            }

            $menu = MenuJson::decodeMenuFromLlmText($result['raw']);
            $items = $this->flattenItems($menu);
            $withBbox = count(array_filter($items, fn ($i) => isset($i['image_bbox'])));
            $this->line(sprintf('    %d items, %d with bbox, %dms', count($items), $withBbox, $result['duration_ms']));

            $confidences = array_values(array_filter(array_map(
                fn ($i) => isset($i['image_bbox']['confidence']) ? (float) $i['image_bbox']['confidence'] : null,
                $items,
            ), fn ($v) => $v !== null));
            if ($confidences !== []) {
                sort($confidences);
                $avg = array_sum($confidences) / count($confidences);
                $this->line(sprintf(
                    '    bbox confidence: min=%.2f avg=%.2f max=%.2f (n=%d)',
                    $confidences[0], $avg, $confidences[count($confidences) - 1], count($confidences),
                ));
            } else {
                $this->line('    bbox confidence: (none returned)');
            }

            $modelDir = $outputRoot.'/'.$this->safeDir($key);
            File::deleteDirectory($modelDir);
            File::ensureDirectoryExists($modelDir);

            $crops = $this->saveCrops($preprocessedPath, $items, $modelDir);

            $summary[$key] = [
                'items' => count($items),
                'bboxes' => $withBbox,
                'crops' => $crops,
                'error' => null,
                'duration_ms' => $result['duration_ms'],
            ];
            $this->line('    → '.$crops.' crops saved to '.$modelDir);
        }

        File::deleteDirectory($workDir);

        $this->line('');
        $this->line('  <info>Summary</info>');
        $this->line('');
        $rows = [];
        foreach ($summary as $key => $s) {
            $rows[] = [$key, $s['items'], $s['bboxes'], $s['crops'], $s['duration_ms'].'ms', $s['error'] ?? '-'];
        }
        $this->table(['Model', 'Items', 'Bboxes', 'Crops', 'Duration', 'Error'], $rows);
        $this->line('  View crops in: '.$outputRoot);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $menu
     * @return list<array<string, mixed>>
     */
    private function flattenItems(array $menu): array
    {
        $items = [];
        foreach ($menu['sections'] ?? [] as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function saveCrops(string $imagePath, array $items, string $destDir): int
    {
        $img = new Imagick($imagePath);
        $w = $img->getImageWidth();
        $h = $img->getImageHeight();

        $saved = 0;
        foreach ($items as $idx => $item) {
            $bbox = $item['image_bbox'] ?? null;
            if (! is_array($bbox) || ! isset($bbox['coords'])) {
                continue;
            }

            [$x1, $y1, $x2, $y2] = $bbox['coords'];

            $cropX = max(0, (int) round($x1 * $w));
            $cropY = max(0, (int) round($y1 * $h));
            $cropW = min((int) round(($x2 - $x1) * $w), $w - $cropX);
            $cropH = min((int) round(($y2 - $y1) * $h), $h - $cropY);

            if ($cropW < 20 || $cropH < 20) {
                $this->line(sprintf(
                    '    skip #%02d coords=[%s] cropXYWH=%d,%d,%d,%d (image %dx%d)',
                    $idx + 1,
                    implode(',', array_map(fn ($v) => is_numeric($v) ? (string) $v : 'X', $bbox['coords'])),
                    $cropX, $cropY, $cropW, $cropH, $w, $h,
                ));

                continue;
            }

            $crop = clone $img;
            $crop->cropImage($cropW, $cropH, $cropX, $cropY);
            $crop->setImagePage(0, 0, 0, 0);
            $crop->setImageFormat('webp');
            $crop->setImageCompressionQuality(85);

            $name = $item['name'] ?? '';
            if (is_array($name)) {
                $name = $name['local'] ?? $name['en'] ?? '';
            }
            $slug = $this->slugify((string) $name);
            $conf = isset($bbox['confidence']) ? (float) $bbox['confidence'] : null;
            $confLabel = $conf !== null ? sprintf('c%02d', (int) round($conf * 100)) : 'cNA';
            $filename = sprintf('%02d_%s_%s.webp', $idx + 1, $confLabel, $slug !== '' ? $slug : 'item');

            $crop->writeImage($destDir.'/'.$filename);
            $crop->clear();
            $crop->destroy();

            $saved++;
        }

        $img->clear();
        $img->destroy();

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $model
     * @return array{raw:string, error:?string, duration_ms:int}
     */
    private function runModel(array $model, string $imagePath, Prompt $prompt): array
    {
        $timeout = (int) config('services.openai_compatible.http_timeout_seconds', 3600);
        $startedAt = microtime(true);

        try {
            if ($model['directHttp']) {
                return $this->runDirect($model, $imagePath, $prompt, $timeout, $startedAt);
            }

            $system = $prompt->system_prompt ? [new SystemMessage($prompt->system_prompt)] : [];
            $user = [new UserMessage($prompt->user_prompt, [Image::fromLocalPath($imagePath)])];

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
            ];
        } catch (Throwable $e) {
            return [
                'raw' => '',
                'error' => mb_substr($e->getMessage(), 0, 200),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $model
     * @return array{raw:string, error:?string, duration_ms:int}
     */
    private function runDirect(array $model, string $imagePath, Prompt $prompt, int $timeout, float $startedAt): array
    {
        $b64 = base64_encode(file_get_contents($imagePath));
        $content = [
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.$b64]],
            ['type' => 'text', 'text' => $prompt->user_prompt],
        ];

        $messages = [];
        if ($prompt->system_prompt) {
            $messages[] = ['role' => 'system', 'content' => $prompt->system_prompt];
        }
        $messages[] = ['role' => 'user', 'content' => $content];

        $body = array_merge([
            'model' => $model['model'],
            'messages' => $messages,
            'max_tokens' => $model['maxTokens'] ?? 32768,
        ], $model['extraBody'] ?? []);

        $response = Http::withToken($model['httpKey'])
            ->timeout($timeout)
            ->post(rtrim($model['httpUrl'], '/').'/chat/completions', $body);

        $data = $response->json();

        if (data_get($data, 'error')) {
            throw new RuntimeException('API Error: '.data_get($data, 'error.message', 'unknown'));
        }

        return [
            'raw' => (string) data_get($data, 'choices.0.message.content', ''),
            'error' => null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function models(): array
    {
        $qwenUrl = rtrim(env('QWEN_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'), '/');
        $qwenKey = env('QWEN_API_KEY', '');
        $openaiUrl = 'https://api.openai.com/v1';
        $openaiKey = env('OPENAI_API_KEY', '');

        return [
            ['key' => 'gemini-2.5-flash', 'provider' => Provider::Gemini, 'model' => 'gemini-2.5-flash', 'options' => ['thinkingBudget' => 0], 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => null, 'extraBody' => []],
            ['key' => 'gemini-2.5-pro', 'provider' => Provider::Gemini, 'model' => 'gemini-2.5-pro', 'options' => [], 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => null, 'extraBody' => []],
            ['key' => 'dashscope/qwen3-vl-plus-2025-12-19', 'provider' => Provider::OpenAI, 'model' => 'qwen3-vl-plus-2025-12-19', 'options' => [], 'directHttp' => true, 'httpUrl' => $qwenUrl, 'httpKey' => $qwenKey, 'maxTokens' => 32768, 'extraBody' => []],
            ['key' => 'dashscope/qwen3-vl-plus-2025-12-19-think', 'provider' => Provider::OpenAI, 'model' => 'qwen3-vl-plus-2025-12-19', 'options' => [], 'directHttp' => true, 'httpUrl' => $qwenUrl, 'httpKey' => $qwenKey, 'maxTokens' => 32768, 'extraBody' => ['enable_thinking' => true, 'thinking_budget' => 38912]],
            ['key' => 'openai/gpt-4.1', 'provider' => Provider::OpenAI, 'model' => 'gpt-4.1-2025-04-14', 'options' => [], 'directHttp' => true, 'httpUrl' => $openaiUrl, 'httpKey' => $openaiKey, 'maxTokens' => 32768, 'extraBody' => []],
            ['key' => 'openrouter/internvl3-78b', 'provider' => Provider::OpenRouter, 'model' => 'opengvlab/internvl3-78b', 'options' => [], 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => 32768, 'extraBody' => []],
            ['key' => 'openrouter/llama-4-maverick', 'provider' => Provider::OpenRouter, 'model' => 'meta-llama/llama-4-maverick', 'options' => [], 'directHttp' => false, 'httpUrl' => '', 'httpKey' => '', 'maxTokens' => 32768, 'extraBody' => []],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $models
     * @return list<array<string, mixed>>
     */
    private function filterModels(array $models): array
    {
        $only = (string) $this->option('model');
        $skip = array_filter(array_map('trim', explode(',', (string) $this->option('skip'))));

        return array_values(array_filter($models, static function ($m) use ($only, $skip) {
            if ($only !== '' && $m['key'] !== $only) {
                return false;
            }

            return ! in_array($m['key'], $skip, true);
        }));
    }

    private function safeDir(string $key): string
    {
        return preg_replace('/[^a-z0-9._-]+/i', '_', $key);
    }

    private function slugify(string $s): string
    {
        $s = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $s) ?? '';
        $s = preg_replace('/\s+/', '_', trim($s)) ?? '';

        return mb_strtolower(mb_substr($s, 0, 40));
    }
}
