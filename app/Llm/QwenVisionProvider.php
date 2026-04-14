<?php

namespace App\Llm;

use Prism\Prism\Enums\Provider;

class QwenVisionProvider extends BaseLlmProvider
{
    public function provider(): Provider
    {
        return Provider::OpenAI;
    }

    public function model(): string
    {
        return 'qwen-vl-max';
    }
}
