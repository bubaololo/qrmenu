<?php

namespace App\Llm;

use App\Contracts\LlmProvider;
use App\Exceptions\LlmRequestFailedException;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Contracts\Message;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Throwable;

abstract class BaseLlmProvider implements LlmProvider
{
    protected function timeoutSeconds(): int
    {
        return (int) config('services.openai_compatible.http_timeout_seconds', 3600);
    }

    protected function maxTokens(): ?int
    {
        return null;
    }

    /** @return array<string, mixed> */
    protected function providerOptions(): array
    {
        return [];
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
            'timeout_seconds' => $this->timeoutSeconds(),
        ], $logContext));

        $startedAt = microtime(true);

        $systemMessages = array_values(array_filter($messages, fn ($m) => $m instanceof SystemMessage));
        $userMessages = array_values(array_filter($messages, fn ($m) => ! ($m instanceof SystemMessage)));

        try {
            $builder = Prism::text()
                ->using($this->provider(), $this->model())
                ->withClientOptions(['timeout' => $this->timeoutSeconds()])
                ->withSystemPrompts($systemMessages)
                ->withMessages($userMessages);

            if (($maxTokens = $this->maxTokens()) !== null) {
                $builder = $builder->withMaxTokens($maxTokens);
            }

            if ($options = $this->providerOptions()) {
                $builder = $builder->withProviderOptions($options);
            }

            $response = $builder->asText();
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
            'finish_reason' => $response->finishReason->name,
            'usage' => $response->usage->toArray(),
            'content_length' => strlen($response->text),
        ], $logContext));

        if ($response->text === '') {
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
            'text' => $response->text,
            'usage' => [
                'input_tokens' => $response->usage->promptTokens ?? null,
                'output_tokens' => $response->usage->completionTokens ?? null,
            ],
        ];
    }
}
