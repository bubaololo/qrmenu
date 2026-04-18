<?php

namespace App\Services;

use App\Support\PreflightResult;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Imagick;

class ImagePreflightService
{
    private const PROMPT = <<<'PROMPT'
Analyze this restaurant menu photo and return STRICT JSON (no prose, no markdown):

{
  "rotation_cw": 0 | 90 | 180 | 270,
  "content_bbox": [x1, y1, x2, y2] | null,
  "quality": "good" | "blurry" | "glare" | "dark"
}

Rules:
- rotation_cw: degrees CW to rotate so text reads left-to-right normally. 0 if already correct.
- content_bbox: normalized [0..1] box of the actual menu within the frame, MEASURED AFTER applying rotation_cw. Use null if the menu fills the frame with <5% margin.
- quality: overall readability. Use "good" by default.

Return only the JSON object.
PROMPT;

    public function analyze(string $sourcePath): PreflightResult
    {
        if (! config('image.preflight.enabled')) {
            return PreflightResult::noop();
        }

        $results = $this->analyzeMany([$sourcePath]);

        return $results[$sourcePath] ?? PreflightResult::noop();
    }

    /**
     * @param  string[]  $paths
     * @return array<string, PreflightResult> keyed by source path
     */
    public function analyzeMany(array $paths): array
    {
        if (empty($paths)) {
            return [];
        }

        if (! config('image.preflight.enabled')) {
            return array_combine($paths, array_map(fn () => PreflightResult::noop(), $paths));
        }

        $llm = Log::channel('llm');
        $batchStart = microtime(true);

        $llm->info('Preflight batch start', [
            'image_count' => count($paths),
        ]);

        $payloads = [];
        foreach ($paths as $path) {
            try {
                $payloads[$path] = $this->buildPayload($path);
            } catch (\Throwable $e) {
                $llm->warning('Preflight payload build failed', [
                    'path' => basename($path),
                    'error' => $e->getMessage(),
                ]);
                $payloads[$path] = null;
            }
        }

        $url = $this->endpoint();
        $timeout = (int) config('image.preflight.timeout', 15);

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn ($path) => $payloads[$path] === null
                ? $pool->as(basename($path))->get('about:blank') // placeholder; will be treated as failed
                : $pool->as(basename($path))->timeout($timeout)->acceptJson()->post($url, $payloads[$path]),
            array_keys($payloads),
        ));

        $results = [];
        $success = 0;
        $failed = 0;

        foreach ($payloads as $path => $payload) {
            $key = basename($path);
            $response = $responses[$key] ?? null;

            if ($payload === null || ! $response instanceof Response || ! $response->successful()) {
                $failed++;
                $llm->warning('Preflight failed, using noop', [
                    'path' => $key,
                    'status' => $response instanceof Response ? $response->status() : null,
                    'error' => $response instanceof Response ? mb_substr((string) $response->body(), 0, 500) : 'no response',
                ]);
                $results[$path] = PreflightResult::noop();

                continue;
            }

            try {
                $results[$path] = $this->parseResponse($response, $path);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $llm->warning('Preflight parse failed, using noop', [
                    'path' => $key,
                    'error' => $e->getMessage(),
                    'raw_response' => mb_substr((string) $response->body(), 0, 500),
                ]);
                $results[$path] = PreflightResult::noop();
            }
        }

        $totalMs = (int) round((microtime(true) - $batchStart) * 1000);

        $llm->info('Preflight batch complete', [
            'image_count' => count($paths),
            'success_count' => $success,
            'failed_count' => $failed,
            'total_duration_ms' => $totalMs,
        ]);

        return $results;
    }

    /** @return array<string, mixed> */
    private function buildPayload(string $sourcePath): array
    {
        $llm = Log::channel('llm');

        $img = new Imagick($sourcePath);
        $origW = $img->getImageWidth();
        $origH = $img->getImageHeight();

        $llm->info('Preflight start', [
            'path' => basename($sourcePath),
            'original_size_kb' => (int) round(filesize($sourcePath) / 1024),
            'original_dims' => $origW.'x'.$origH,
        ]);

        // Strip EXIF orientation for preflight (we want LLM to see raw pixels
        // to judge whether rotation is needed).
        $img->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

        $maxDim = (int) config('image.preflight.max_dim', 384);
        if (max($img->getImageWidth(), $img->getImageHeight()) > $maxDim) {
            $img->resizeImage($maxDim, $maxDim, Imagick::FILTER_LANCZOS, 1, true);
        }

        $img->setImageFormat('jpeg');
        $img->setImageCompressionQuality(70);

        $dw = $img->getImageWidth();
        $dh = $img->getImageHeight();
        $blob = $img->getImageBlob();
        $img->clear();
        $img->destroy();

        $b64 = base64_encode($blob);

        $llm->debug('Preflight downsampled', [
            'path' => basename($sourcePath),
            'downsampled_dims' => $dw.'x'.$dh,
            'base64_kb' => (int) round(strlen($b64) / 1024),
        ]);

        return [
            'contents' => [[
                'parts' => [
                    ['text' => self::PROMPT],
                    ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $b64]],
                ],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0,
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ];
    }

    private function parseResponse(Response $response, string $sourcePath): PreflightResult
    {
        $body = $response->json();
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

        $decoded = json_decode((string) $text, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Response text is not JSON: '.mb_substr((string) $text, 0, 200));
        }

        $rotation = (int) ($decoded['rotation_cw'] ?? 0);
        if (! in_array($rotation, [0, 90, 180, 270], true)) {
            $rotation = 0;
        }

        $bbox = null;
        if (isset($decoded['content_bbox']) && is_array($decoded['content_bbox']) && count($decoded['content_bbox']) === 4) {
            $raw = array_values($decoded['content_bbox']);
            $bbox = [
                max(0.0, min(1.0, (float) $raw[0])),
                max(0.0, min(1.0, (float) $raw[1])),
                max(0.0, min(1.0, (float) $raw[2])),
                max(0.0, min(1.0, (float) $raw[3])),
            ];
            if ($bbox[2] - $bbox[0] < 0.1 || $bbox[3] - $bbox[1] < 0.1) {
                $bbox = null; // too small, ignore
            }
        }

        $quality = (string) ($decoded['quality'] ?? 'good');
        if (! in_array($quality, ['good', 'blurry', 'glare', 'dark'], true)) {
            $quality = 'good';
        }

        $result = new PreflightResult($rotation, $bbox, $quality);
        $usage = $body['usageMetadata'] ?? [];

        Log::channel('llm')->info('Preflight result', [
            'path' => basename($sourcePath),
            'rotation_cw' => $result->rotationCw,
            'content_bbox' => $result->contentBbox,
            'quality' => $result->quality,
            'input_tokens' => $usage['promptTokenCount'] ?? null,
            'output_tokens' => $usage['candidatesTokenCount'] ?? null,
        ]);

        return $result;
    }

    private function endpoint(): string
    {
        $base = rtrim((string) config('prism.providers.gemini.url', 'https://generativelanguage.googleapis.com/v1beta/models'), '/');
        $model = (string) config('image.preflight.model', 'gemini-2.5-flash-lite');
        $key = (string) config('prism.providers.gemini.api_key', '');

        return "{$base}/{$model}:generateContent?key={$key}";
    }
}
