<?php

namespace App\Actions;

use App\Exceptions\LlmRequestFailedException;
use App\Models\Prompt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AnalyzeMenuImageAction
{
    /** OpenAI-compatible `/v1/chat/completions` endpoint (provider: Alibaba Cloud international). */
    private const CHAT_COMPLETIONS_URL = 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions';

    private const MODEL = 'qwen-vl-max';

    private const TYPE_SLUG = 'menu_analyzer';

    private const HTTP_TIMEOUT_MIN_SECONDS = 60;

    private const HTTP_TIMEOUT_MAX_SECONDS = 3600;

    /**
     * @param  string|string[]  $images  Local storage paths (disk: public) or data URIs
     */
    public function handle(string|array $images): string
    {
        $timeoutSeconds = $this->resolveHttpTimeoutSeconds();

        $prompt = Prompt::activeForType(self::TYPE_SLUG)
            ?? throw new RuntimeException('No active prompt found for type "'.self::TYPE_SLUG.'".');

        $images = (array) $images;

        $content = [];
        foreach ($images as $image) {
            $dataUri = $this->toDataUri($image);
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $dataUri]];
        }
        $content[] = ['type' => 'text', 'text' => $prompt->user_prompt];

        $messages = [];
        if ($prompt->system_prompt) {
            $messages[] = ['role' => 'system', 'content' => $prompt->system_prompt];
        }
        $messages[] = ['role' => 'user', 'content' => $content];

        $sanitizedPayload = [
            'model' => self::MODEL,
            'messages' => $this->sanitizeMessagesForLog($messages),
        ];

        $startedAt = microtime(true);

        info('LLM outbound request', [
            'url' => self::CHAT_COMPLETIONS_URL,
            'model' => self::MODEL,
            'timeout_seconds' => $timeoutSeconds,
            'image_count' => count($images),
            'paths' => $images,
            'prompt_id' => $prompt->id,
            'prompt_name' => $prompt->name,
            'payload' => $sanitizedPayload,
        ]);

        try {
            $response = Http::withToken(env('QWEN_API_KEY'))
                ->timeout($timeoutSeconds)
                ->withOptions(['proxy' => ''])
                ->post(self::CHAT_COMPLETIONS_URL, [
                    'model' => self::MODEL,
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $telemetry = $this->buildFailureTelemetry($durationMs, $sanitizedPayload, null, $e, $timeoutSeconds);
            info('LLM connection failed', $telemetry);

            throw new LlmRequestFailedException($e->getMessage(), $telemetry, 0, $e);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        try {
            $response->throw();
        } catch (RequestException $e) {
            $telemetry = $this->buildFailureTelemetry($durationMs, $sanitizedPayload, $e->response, $e, $timeoutSeconds);
            info('LLM HTTP error', $telemetry);

            throw new LlmRequestFailedException($e->getMessage(), $telemetry, 0, $e);
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || $text === '') {
            $telemetry = $this->buildFailureTelemetry($durationMs, $sanitizedPayload, $response, null, $timeoutSeconds);
            $telemetry['response']['note'] = 'choices.0.message.content missing or empty';
            info('LLM empty assistant message', $telemetry);

            throw new LlmRequestFailedException(
                'LLM returned no assistant text (see debug.response for provider body).',
                $telemetry
            );
        }

        info('LLM response success', [
            'duration_ms' => $durationMs,
            'status' => $response->status(),
            'usage' => $response->json('usage'),
            'finish_reason' => $response->json('choices.0.finish_reason'),
            'content_length' => strlen($text),
            'raw' => $text,
        ]);

        return $text;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    private function sanitizeMessagesForLog(array $messages): array
    {
        return array_map(fn (array $msg): array => $this->sanitizeMessageForLog($msg), $messages);
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array<string, mixed>
     */
    private function sanitizeMessageForLog(array $msg): array
    {
        $role = $msg['role'] ?? null;
        $content = $msg['content'] ?? null;
        if (is_string($content)) {
            return [
                'role' => $role,
                'content' => $this->truncateText($content, 500),
            ];
        }
        if (! is_array($content)) {
            return $msg;
        }

        $sanitized = [];
        foreach ($content as $part) {
            if (! is_array($part)) {
                $sanitized[] = $part;

                continue;
            }
            if (($part['type'] ?? '') === 'image_url') {
                $url = $part['image_url']['url'] ?? '';

                $sanitized[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $this->sanitizeDataUriForLog(is_string($url) ? $url : ''),
                    ],
                ];

                continue;
            }
            if (($part['type'] ?? '') === 'text') {
                $t = is_string($part['text'] ?? null) ? $part['text'] : '';

                $sanitized[] = [
                    'type' => 'text',
                    'text' => $this->truncateText($t, 4000),
                ];

                continue;
            }
            $sanitized[] = $part;
        }

        return ['role' => $role, 'content' => $sanitized];
    }

    private function sanitizeDataUriForLog(string $url): string
    {
        if (! str_starts_with($url, 'data:')) {
            return $url;
        }
        if (preg_match('#^data:([^;]+);base64,(.+)$#s', $url, $m)) {
            return 'data:'.$m[1].';base64,<omitted len='.strlen($m[2]).' chars>';
        }

        return 'data:<omitted len='.strlen($url).' chars>';
    }

    private function truncateText(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars).'… (total '.mb_strlen($text).' chars)';
    }

    /**
     * @param  array<string, mixed>  $sanitizedPayload
     * @return array<string, mixed>
     */
    private function buildFailureTelemetry(
        int $durationMs,
        array $sanitizedPayload,
        ?Response $response,
        ?Throwable $previous = null,
        int $timeoutSeconds = 3600,
    ): array {
        $out = [
            'duration_ms' => $durationMs,
            'request' => [
                'url' => self::CHAT_COMPLETIONS_URL,
                'method' => 'POST',
                'timeout_seconds' => $timeoutSeconds,
                'payload' => $sanitizedPayload,
            ],
        ];
        if ($previous !== null) {
            $out['exception'] = [
                'class' => $previous::class,
                'message' => $previous->getMessage(),
            ];
        }
        if ($response !== null) {
            $body = $response->body();
            $out['response'] = [
                'http_status' => $response->status(),
                'body_preview' => Str::limit($body, 120000, '…[truncated]'),
                'header_x_dashscope_partialresponse' => $response->header('x-dashscope-partialresponse'), // vendor-specific (partial body on timeout)
            ];
            $decoded = $response->json();
            if (is_array($decoded)) {
                $out['response']['json'] = $decoded;
            }
        }

        return $out;
    }

    private function toDataUri(string $path): string
    {
        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        $contents = Storage::disk('public')->get($path);
        $fullPath = Storage::disk('public')->path($path);
        $mime = mime_content_type($fullPath) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function resolveHttpTimeoutSeconds(): int
    {
        $configured = (int) config('services.openai_compatible.http_timeout_seconds', 3600);

        return max(self::HTTP_TIMEOUT_MIN_SECONDS, min($configured, self::HTTP_TIMEOUT_MAX_SECONDS));
    }
}
