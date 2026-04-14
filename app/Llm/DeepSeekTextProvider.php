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
}
