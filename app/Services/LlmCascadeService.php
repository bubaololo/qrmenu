<?php

namespace App\Services;

use App\Contracts\LlmProvider;
use App\Enums\LlmRequestStatus;
use App\Exceptions\LlmRequestFailedException;
use App\Llm\GeminiVisionProvider;
use App\Llm\OpenRouterProvider;
use App\Llm\QwenDirectProvider;
use App\Models\LlmRequest;
use App\Models\MenuAnalysis;
use Illuminate\Support\Facades\Log;
use Laravel\Pulse\Facades\Pulse;
use Prism\Prism\Contracts\Message;

class LlmCascadeService
{
    /**
     * Resolve the ordered list of LlmProvider instances for a given image count.
     * When $modelOverride is set (user explicitly chose a model), returns a single-element list.
     *
     * @return list<LlmProvider>
     */
    public function resolveProviders(int $imageCount, ?string $modelOverride = null): array
    {
        if ($modelOverride !== null) {
            return [$this->instantiateProvider(['model' => $modelOverride])];
        }

        $tierKey = $imageCount <= config('llm.thresholds.small_max_images', 4) ? 'small' : 'large';

        /** @var list<array<string, mixed>> $tierConfig */
        $tierConfig = config("llm.tiers.{$tierKey}", []);

        return array_map(
            fn (array $entry) => $this->instantiateProvider($entry),
            $tierConfig
        );
    }

    /**
     * Execute analysis with cascading fallback. Tries each provider in order,
     * logs every attempt to llm_requests. Returns on first success.
     *
     * @param  Message[]  $messages
     * @param  list<LlmProvider>  $providers
     * @param  array<string, mixed>  $logContext
     * @return array{text: string, provider: string, model: string, tier: int}
     *
     * @throws LlmRequestFailedException When all providers exhausted
     */
    public function executeWithFallback(
        array $messages,
        array $providers,
        ?MenuAnalysis $analysis = null,
        array $logContext = [],
    ): array {
        $lastException = null;
        $llm = Log::channel('llm');

        $llm->info('Cascade start', array_merge([
            'analysis_uuid' => $analysis?->uuid,
            'provider_chain' => array_map(
                fn ($p) => $p->provider()->value.':'.$p->model(),
                $providers,
            ),
        ], $logContext));

        foreach ($providers as $tier => $provider) {
            $startedAt = microtime(true);

            try {
                $result = $provider->execute($messages, $logContext);
                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

                $this->logRequest($analysis, $provider, $tier, LlmRequestStatus::Success, $durationMs, $logContext, $result['text'], usage: $result['usage']);

                $llm->info('Cascade succeeded', [
                    'analysis_uuid' => $analysis?->uuid,
                    'tier' => $tier,
                    'provider' => $provider->provider()->value,
                    'model' => $provider->model(),
                    'duration_ms' => $durationMs,
                ]);

                return [
                    'text' => $result['text'],
                    'provider' => $provider->provider()->value,
                    'model' => $provider->model(),
                    'tier' => $tier,
                ];
            } catch (LlmRequestFailedException $e) {
                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                $lastException = $e;

                $status = $this->classifyError($e);
                $this->logRequest($analysis, $provider, $tier, $status, $durationMs, $logContext, error: $e);

                $llm->warning('Cascade fallback', [
                    'analysis_uuid' => $analysis?->uuid,
                    'tier' => $tier,
                    'provider' => $provider->provider()->value,
                    'model' => $provider->model(),
                    'error' => $e->getMessage(),
                    'duration_ms' => $durationMs,
                    'remaining_providers' => count($providers) - $tier - 1,
                ]);
            }
        }

        $llm->error('Cascade exhausted', [
            'analysis_uuid' => $analysis?->uuid,
            'tried_providers' => count($providers),
            'last_error' => $lastException?->getMessage(),
        ]);

        throw $lastException ?? new LlmRequestFailedException('No providers configured.', []);
    }

    /**
     * @param  array<string, mixed>  $entry  Tier config entry: `model`, optional `provider`, optional `provider_routing`.
     */
    private function instantiateProvider(array $entry): LlmProvider
    {
        $model = (string) $entry['model'];
        $providerType = $entry['provider'] ?? $this->guessProviderType($model);

        return match ($providerType) {
            'gemini' => app(GeminiVisionProvider::class),
            'openrouter' => app()->makeWith(OpenRouterProvider::class, [
                'openRouterModel' => $model,
                'providerRouting' => $entry['provider_routing'] ?? [],
            ]),
            'qwen-direct' => app()->makeWith(QwenDirectProvider::class, ['qwenModel' => $model]),
            default => throw new \InvalidArgumentException("Unknown provider type: {$providerType}"),
        };
    }

    private function guessProviderType(string $model): string
    {
        return match (true) {
            str_starts_with($model, 'gemini') => 'gemini',
            str_starts_with($model, 'qwen') => 'qwen-direct',
            default => 'openrouter',
        };
    }

    /**
     * @param  array{input_tokens: ?int, output_tokens: ?int}|null  $usage
     */
    private function logRequest(
        ?MenuAnalysis $analysis,
        LlmProvider $provider,
        int $tier,
        LlmRequestStatus $status,
        int $durationMs,
        array $logContext,
        ?string $responseText = null,
        ?LlmRequestFailedException $error = null,
        ?array $usage = null,
    ): void {
        $promptTokens = $usage['input_tokens'] ?? $error?->telemetry['usage']['input_tokens'] ?? null;
        $completionTokens = $usage['output_tokens'] ?? $error?->telemetry['usage']['output_tokens'] ?? null;

        LlmRequest::create([
            'menu_analysis_id' => $analysis?->id,
            'provider' => $provider->provider()->value,
            'model' => $provider->model(),
            'tier_position' => $tier,
            'status' => $status,
            'image_count' => $logContext['image_count'] ?? 0,
            'duration_ms' => $durationMs,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'response_length' => $responseText !== null ? strlen($responseText) : null,
            'finish_reason' => null,
            'error_class' => $error !== null ? ($error->getPrevious()?->class ?? $error::class) : null,
            'error_message' => $error?->getMessage(),
            'prompt_id' => $logContext['prompt_id'] ?? null,
        ]);

        $this->recordPulseMetrics($provider, $tier, $status, $durationMs, $promptTokens, $completionTokens);
    }

    private function recordPulseMetrics(
        LlmProvider $provider,
        int $tier,
        LlmRequestStatus $status,
        int $durationMs,
        ?int $promptTokens,
        ?int $completionTokens,
    ): void {
        $key = $provider->provider()->value.':'.$provider->model();

        Pulse::record('llm_request', $key, $durationMs)->avg()->count();

        if ($status !== LlmRequestStatus::Success) {
            Pulse::record('llm_error', $key, 1)->count();
        }

        if ($tier > 0) {
            Pulse::record('llm_fallback', $key, 1)->count();
        }

        $totalTokens = ($promptTokens ?? 0) + ($completionTokens ?? 0);
        if ($totalTokens > 0) {
            Pulse::record('llm_tokens', $key, $totalTokens)->sum();
        }

        $costCents = $this->calculateCostCents($provider->model(), $promptTokens, $completionTokens);
        if ($costCents > 0) {
            Pulse::record('llm_cost', $key, $costCents)->sum();
        }
    }

    private function calculateCostCents(string $model, ?int $promptTokens, ?int $completionTokens): float
    {
        /** @var array{input: int, output: int}|null $pricing */
        $pricing = config("llm.pricing.{$model}");
        if ($pricing === null) {
            return 0;
        }

        $inputCost = ($promptTokens ?? 0) / 1_000_000 * $pricing['input'];
        $outputCost = ($completionTokens ?? 0) / 1_000_000 * $pricing['output'];

        return $inputCost + $outputCost;
    }

    private function classifyError(LlmRequestFailedException $e): LlmRequestStatus
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'cURL error 28') || str_contains($msg, 'timed out')) {
            return LlmRequestStatus::Timeout;
        }

        if (str_contains($msg, 'no text') || str_contains($msg, 'Empty response')) {
            return LlmRequestStatus::EmptyResponse;
        }

        return LlmRequestStatus::Error;
    }
}
