<?php

namespace App\Llm;

use Prism\Prism\Enums\Provider;

/**
 * Qwen VL via DashScope's OpenAI-compatible /chat/completions endpoint.
 * Prism routes OpenAI-type providers through /responses which DashScope doesn't support.
 */
class QwenDirectProvider extends DirectHttpLlmProvider
{
    public function __construct(private string $qwenModel = 'qwen3-vl-plus-2025-12-19') {}

    public function provider(): Provider
    {
        return Provider::OpenAI;
    }

    public function model(): string
    {
        return $this->qwenModel;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) env('QWEN_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'), '/');
    }

    protected function apiKey(): string
    {
        return (string) env('QWEN_API_KEY', '');
    }

    protected function maxTokens(): int
    {
        return 32768;
    }
}
