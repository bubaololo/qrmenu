<?php

namespace App\Llm;

use Prism\Prism\Enums\Provider;

class GeminiVisionProvider extends BaseLlmProvider
{
    public function provider(): Provider
    {
        return Provider::Gemini;
    }

    public function model(): string
    {
        return 'gemini-2.5-flash';
    }

    public function timeoutSeconds(): int
    {
        $configured = (int) config('services.openai_compatible.http_timeout_seconds', 3600);

        return max(60, min($configured, 3600));
    }

    protected function providerOptions(): array
    {
        return ['thinkingBudget' => 0];
    }
}
