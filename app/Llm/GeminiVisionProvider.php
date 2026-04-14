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

    protected function providerOptions(): array
    {
        return ['thinkingBudget' => 0];
    }
}
