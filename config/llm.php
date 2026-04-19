<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model Cascade Tiers
    |--------------------------------------------------------------------------
    |
    | Ordered lists of LLM providers to try for menu analysis. On failure,
    | the next provider in the tier is attempted. Tiers are selected based
    | on image count (small vs large packs).
    |
    | provider: 'gemini' | 'openrouter' | 'qwen-direct'
    |   gemini       → Prism Provider::Gemini
    |   openrouter   → Prism Provider::OpenRouter
    |   qwen-direct  → Direct HTTP to DashScope /chat/completions
    |
    */

    'tiers' => [
        'small' => [
            ['provider' => 'qwen-direct', 'model' => 'qwen3-vl-plus-2025-12-19'],
            ['provider' => 'gemini', 'model' => 'gemini-2.5-flash'],
            ['provider' => 'openrouter', 'model' => 'google/gemma-4-26b-a4b-it:free'],
        ],
        'large' => [
            ['provider' => 'qwen-direct', 'model' => 'qwen3-vl-plus-2025-12-19'],
            [
                'provider' => 'openrouter',
                'model' => 'google/gemma-4-31b-it',
                // DeepInfra's gemma-4-31b endpoint is text-only and 404s on image inputs;
                // AkashML caps context at 131K. Let OR auto-route across Novita / Parasail /
                // Together (all bf16, 262K).
                'provider_routing' => ['ignore' => ['DeepInfra', 'AkashML']],
            ],
            ['provider' => 'gemini', 'model' => 'gemini-2.5-flash'],
        ],
    ],

    'thresholds' => [
        'small_max_images' => 4,
        'chunk_size' => 4,                // N images per chunk when chunking is triggered
        'chunk_when_images_gt' => 5,      // chunk if image_count > this
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Chunk Job Timeout
    |--------------------------------------------------------------------------
    */

    'chunk_job_timeout' => (int) env('LLM_CHUNK_JOB_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Translation Chunking
    |--------------------------------------------------------------------------
    | Large menus overflow DeepSeek's 8K output cap in a single call. We chunk
    | the TSV payload into batches and run one DeepSeek request per batch.
    */

    'translation' => [
        'chunk_lines' => (int) env('LLM_TRANSLATION_CHUNK_LINES', 80),
        'openrouter_fallback_model' => env('LLM_TRANSLATION_OR_FALLBACK_MODEL', 'openai/gpt-4.1-mini'),
    ],

    'deepseek' => [
        'max_tokens' => (int) env('LLM_DEEPSEEK_MAX_TOKENS', 8000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => env('LLM_QUEUE', 'llm-analysis'),
    'job_timeout' => (int) env('LLM_JOB_TIMEOUT', 1000),

    /*
    |--------------------------------------------------------------------------
    | Token Pricing (cents per 1M tokens)
    |--------------------------------------------------------------------------
    */

    'pricing' => [
        'gemini-2.5-flash' => ['input' => 15, 'output' => 125],
        'qwen3-vl-plus' => ['input' => 20, 'output' => 160],
        'qwen3-vl-plus-2025-12-19' => ['input' => 20, 'output' => 160],
        'google/gemma-4-26b-a4b-it' => ['input' => 7, 'output' => 40],
        'google/gemma-4-26b-a4b-it:free' => ['input' => 0, 'output' => 0],
        'google/gemma-4-31b-it' => ['input' => 13, 'output' => 38],
        'deepseek-chat' => ['input' => 27, 'output' => 110],
        'openai/gpt-4.1-mini' => ['input' => 40, 'output' => 160],
    ],

];
