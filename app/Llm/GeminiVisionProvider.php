<?php

namespace App\Llm;

use App\Services\GeminiCacheService;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

class GeminiVisionProvider extends BaseLlmProvider
{
    private ?string $cachedContentName = null;

    public function __construct(private readonly GeminiCacheService $cacheService) {}

    public function provider(): Provider
    {
        return Provider::Gemini;
    }

    public function model(): string
    {
        return 'gemini-2.5-flash';
    }

    /**
     * @param  array<mixed>  $messages
     * @param  array<string, mixed>  $logContext
     * @return array{text: string, usage: array{input_tokens: ?int, output_tokens: ?int}}
     */
    public function execute(array $messages, array $logContext = []): array
    {
        $systemMessages = array_values(array_filter($messages, fn ($m) => $m instanceof SystemMessage));
        $userMessages = array_values(array_filter($messages, fn ($m) => ! ($m instanceof SystemMessage)));

        if (! empty($systemMessages)) {
            $this->cachedContentName = $this->cacheService->resolve($this->model(), $systemMessages);
            if ($this->cachedContentName !== null) {
                $messages = $userMessages;
            }
        }

        try {
            return parent::execute($messages, array_merge($logContext, [
                'cache_hit' => $this->cachedContentName !== null,
            ]));
        } finally {
            $this->cachedContentName = null;
        }
    }

    /** @return array<string, mixed> */
    protected function providerOptions(): array
    {
        $opts = ['thinkingBudget' => 0];

        if ($this->cachedContentName !== null) {
            $opts['cachedContentName'] = $this->cachedContentName;
        }

        return $opts;
    }
}
