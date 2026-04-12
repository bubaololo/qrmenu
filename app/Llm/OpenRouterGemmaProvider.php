<?php

namespace App\Llm;

use Prism\Prism\Enums\Provider;

class OpenRouterGemmaProvider extends BaseLlmProvider
{
    public function provider(): Provider
    {
        return Provider::OpenRouter;
    }

    public function model(): string
    {
        return 'google/gemma-4-26b-a4b-it:free';
    }

    public function timeoutSeconds(): int
    {
        return 120;
    }
}
