<?php

namespace App\Llm;

use App\Contracts\LlmProvider;
use App\Exceptions\LlmRequestFailedException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Contracts\Message;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Text;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Throwable;

/**
 * LLM provider that calls OpenAI-compatible /chat/completions directly via HTTP.
 * Used for providers where Prism's /responses endpoint doesn't work (DashScope, etc.).
 */
abstract class DirectHttpLlmProvider implements LlmProvider
{
    abstract public function provider(): Provider;

    abstract public function model(): string;

    abstract protected function baseUrl(): string;

    abstract protected function apiKey(): string;

    protected function maxTokens(): int
    {
        return 32768;
    }

    protected function timeoutSeconds(): int
    {
        return (int) config('services.openai_compatible.http_timeout_seconds', 3600);
    }

    /**
     * @param  Message[]  $messages
     * @param  array<string, mixed>  $logContext
     * @return array{text: string, usage: array{input_tokens: ?int, output_tokens: ?int}}
     */
    public function execute(array $messages, array $logContext = []): array
    {
        $llm = Log::channel('llm');

        $llm->info('LLM request', array_merge([
            'provider' => $this->provider()->value,
            'model' => $this->model(),
            'transport' => 'direct_http',
            'timeout_seconds' => $this->timeoutSeconds(),
        ], $logContext));

        $startedAt = microtime(true);

        $payload = $this->buildPayload($messages);

        try {
            $response = Http::withToken($this->apiKey())
                ->timeout($this->timeoutSeconds())
                ->post(rtrim($this->baseUrl(), '/').'/chat/completions', $payload);

            $data = $response->json();

            if (data_get($data, 'error')) {
                throw new \RuntimeException(
                    'API Error: '.data_get($data, 'error.message', json_encode(data_get($data, 'error')))
                );
            }

            $text = (string) data_get($data, 'choices.0.message.content', '');
            $finishReason = data_get($data, 'choices.0.finish_reason');
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $telemetry = array_merge([
                'provider' => $this->provider()->value,
                'model' => $this->model(),
                'duration_ms' => $durationMs,
                'exception' => [
                    'class' => $e::class,
                    'message' => $e->getMessage(),
                ],
            ], $logContext);

            $llm->error('LLM request failed', $telemetry);

            throw new LlmRequestFailedException($e->getMessage(), $telemetry, 0, $e);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $llm->info('LLM response success', array_merge([
            'duration_ms' => $durationMs,
            'finish_reason' => $finishReason,
            'usage' => [
                'input_tokens' => data_get($data, 'usage.prompt_tokens'),
                'output_tokens' => data_get($data, 'usage.completion_tokens'),
            ],
            'content_length' => strlen($text),
        ], $logContext));

        if ($text === '') {
            $telemetry = array_merge([
                'provider' => $this->provider()->value,
                'model' => $this->model(),
                'duration_ms' => $durationMs,
                'note' => 'Empty response text from provider',
            ], $logContext);

            $llm->warning('LLM empty response', $telemetry);

            throw new LlmRequestFailedException('LLM returned no text.', $telemetry);
        }

        return [
            'text' => $text,
            'usage' => [
                'input_tokens' => data_get($data, 'usage.prompt_tokens'),
                'output_tokens' => data_get($data, 'usage.completion_tokens'),
            ],
        ];
    }

    /**
     * @param  Message[]  $messages
     * @return array<string, mixed>
     */
    private function buildPayload(array $messages): array
    {
        $apiMessages = [];

        foreach ($messages as $message) {
            if ($message instanceof SystemMessage) {
                $apiMessages[] = ['role' => 'system', 'content' => $message->content];

                continue;
            }

            if ($message instanceof UserMessage) {
                $content = [];

                foreach ($message->additionalContent as $part) {
                    if ($part instanceof Image) {
                        $mime = $part->mimeType() ?? 'image/jpeg';
                        $b64 = $part->base64();
                        $content[] = [
                            'type' => 'image_url',
                            'image_url' => ['url' => "data:{$mime};base64,{$b64}"],
                        ];
                    } elseif ($part instanceof Text) {
                        $content[] = ['type' => 'text', 'text' => $part->text];
                    }
                }

                $apiMessages[] = ['role' => 'user', 'content' => $content];
            }
        }

        return [
            'model' => $this->model(),
            'messages' => $apiMessages,
            'max_tokens' => $this->maxTokens(),
        ];
    }
}
