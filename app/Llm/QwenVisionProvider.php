<?php

namespace App\Llm;

use Prism\Prism\Enums\Provider;

class QwenVisionProvider extends BaseLlmProvider
{
    private const MIN_TIMEOUT = 60;

    private const MAX_TIMEOUT = 3600;

    public function provider(): Provider
    {
        return Provider::OpenAI;
    }

    public function model(): string
    {
        return 'qwen-vl-max';
    }

    public function timeoutSeconds(): int
    {
        $configured = (int) config('services.openai_compatible.http_timeout_seconds', 3600);

        return max(self::MIN_TIMEOUT, min($configured, self::MAX_TIMEOUT));
    }
}
