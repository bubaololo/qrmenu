<?php

namespace App\Llm;

use Prism\Prism\Enums\Provider;

class OpenRouterProvider extends BaseLlmProvider
{
    public function __construct(private string $openRouterModel = 'google/gemma-4-26b-a4b-it:free') {}

    public function provider(): Provider
    {
        return Provider::OpenRouter;
    }

    public function model(): string
    {
        return $this->openRouterModel;
    }
}
