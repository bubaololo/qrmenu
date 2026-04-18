<?php

namespace App\Llm;

use Prism\Prism\Enums\Provider;

class DeepSeekTextProvider extends BaseLlmProvider
{
    public function provider(): Provider
    {
        return Provider::DeepSeek;
    }

    public function model(): string
    {
        return 'deepseek-chat';
    }

    protected function maxTokens(): ?int
    {
        // deepseek-chat supports up to 8192 output tokens.
        // Default is 4096 which truncates big payloads and trips Prism's handler
        // (finish_reason=length → PrismException: unknown finish reason).
        return (int) config('llm.deepseek.max_tokens', 8000);
    }
}
