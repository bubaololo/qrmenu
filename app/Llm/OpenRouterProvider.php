<?php

namespace App\Llm;

use Prism\Prism\Enums\Provider;

class OpenRouterProvider extends BaseLlmProvider
{
    /**
     * @param  array<string, mixed>  $providerRouting  OpenRouter `provider` routing body param —
     *                                                 e.g. `['only' => ['deepinfra/fp8']]` to pin a specific host.
     */
    public function __construct(
        private string $openRouterModel = 'google/gemma-4-26b-a4b-it:free',
        private array $providerRouting = [],
    ) {}

    public function provider(): Provider
    {
        return Provider::OpenRouter;
    }

    public function model(): string
    {
        return $this->openRouterModel;
    }

    protected function providerOptions(): array
    {
        return $this->providerRouting === []
            ? []
            : ['provider' => $this->providerRouting];
    }
}
